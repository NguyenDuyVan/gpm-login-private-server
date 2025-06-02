<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Group;
use App\Models\GroupRole;
use App\Models\ProfileRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProfileService
{
    /**
     * Get profiles with filters and pagination
     *
     * @param User $user
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getProfiles(User $user, array $filters = [])
    {
        // Default, show all profiles
        $query = Profile::with(['createdUser', 'lastRunUser', 'group']);

        // If user isn't admin, show by role
        if ($user->role < 2) {
            $ids_group_share = DB::table('group_roles')->where('user_id', $user->id)->pluck('group_id');

            $query = Profile::whereIn('id', function ($subQuery) use ($user, $ids_group_share) {
                $subQuery->select('profiles.id')
                    ->from('profiles')
                    ->join('profile_roles', 'profiles.id', '=', 'profile_roles.profile_id')
                    ->where(function($q) use ($user, $ids_group_share) {
                        $q->where('profile_roles.user_id', $user->id)
                          ->orWhereIn('profiles.group_id', $ids_group_share);
                    });
            })->with(['createdUser', 'lastRunUser', 'group']);
        }

        // Apply filters
        $this->applyFilters($query, $user, $filters);

        // Pagination
        $perPage = $filters['per_page'] ?? 30;
        return $query->paginate($perPage);
    }

    /**
     * Apply filters to query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param User $user
     * @param array $filters
     */
    private function applyFilters($query, User $user, array $filters)
    {
        // Filter by group
        if (isset($filters['group_id']) && $filters['group_id'] != Group::where('name', 'All')->first()->id) {
            $query->where('group_id', $filters['group_id']);
        } else {
            $query->where('group_id', '!=', 0); // exclude trash
        }

        // Search
        if (isset($filters['search'])) {
            if (!str_contains($filters['search'], 'author:')) {
                $query->where('name', 'like', "%{$filters['search']}%");
            } else {
                $authorName = str_replace('author:', '', $filters['search']);
                $createdUser = User::where('display_name', $authorName)->first();
                if ($createdUser != null) {
                    $query->where('created_by', $createdUser->id);
                }
            }
        }

        // Share mode filter
        if (isset($filters['share_mode'])) {
            if ($filters['share_mode'] == 1) { // No share
                $query->where('created_by', $user->id);
            } else {
                $query->where('created_by', '!=', $user->id);
            }
        }

        // Filter by tags
        if (isset($filters['tags'])) {
            $tags = explode(",", $filters['tags']);
            foreach ($tags as $tag) {
                if ($tag == $tags[0]) {
                    $query->whereJsonContains('json_data->Tags', $tag);
                } else {
                    $query->orWhereJsonContains('json_data->Tags', $tag);
                }
            }
        }

        // Sort
        if (isset($filters['sort'])) {
            switch ($filters['sort']) {
                case 'created':
                    $query->orderBy('created_at');
                    break;
                case 'created_at_desc':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'name':
                    $query->orderBy('name');
                    break;
                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;
            }
        }
    }

    /**
     * Create a new profile
     *
     * @param string $name
     * @param string $s3Path
     * @param string $jsonData
     * @param string|null $cookieData
     * @param int $groupId
     * @param int $userId
     * @return Profile
     */
    public function createProfile(string $name, string $s3Path, string $jsonData, ?string $cookieData, int $groupId, int $userId)
    {
        $profile = new Profile();
        $profile->name = $name;
        $profile->s3_path = $s3Path;
        $profile->json_data = $jsonData;
        $profile->cookie_data = $cookieData ?? '[]';
        $profile->group_id = $groupId;
        $profile->created_by = $userId;
        $profile->status = 1;
        $profile->last_run_at = null;
        $profile->last_run_by = null;
        $profile->save();

        // Create profile role for creator
        $profileRole = new ProfileRole();
        $profileRole->profile_id = $profile->id;
        $profileRole->user_id = $userId;
        $profileRole->role = 2;
        $profileRole->save();

        return Profile::where('id', $profile->id)->with(['createdUser', 'lastRunUser', 'group'])->first();
    }

    /**
     * Get profile by ID
     *
     * @param int $id
     * @param User $user
     * @return array
     */
    public function getProfile(int $id, User $user)
    {
        if (!$this->canAccessProfile($id, $user)) {
            return ['success' => false, 'message' => 'Không đủ quyền với profile', 'data' => null];
        }

        $profile = Profile::find($id);
        if ($profile == null) {
            return ['success' => false, 'message' => 'Profile không tồn tại', 'data' => null];
        }

        return ['success' => true, 'message' => 'Thành công', 'data' => $profile];
    }

    /**
     * Update profile
     *
     * @param int $id
     * @param string $name
     * @param string $s3Path
     * @param string $jsonData
     * @param string $cookieData
     * @param int $groupId
     * @param string|null $lastRunAt
     * @param int|null $lastRunBy
     * @param User $user
     * @return array
     */
    public function updateProfile(int $id, string $name, string $s3Path, string $jsonData, string $cookieData, int $groupId, ?string $lastRunAt, ?int $lastRunBy, User $user)
    {
        if (!$this->canModifyProfile($id, $user)) {
            return ['success' => false, 'message' => 'Không đủ quyền sửa profile', 'data' => null];
        }

        $profile = Profile::find($id);
        if ($profile == null) {
            return ['success' => false, 'message' => 'Profile không tồn tại', 'data' => null];
        }

        $profile->name = $name;
        $profile->s3_path = $s3Path;
        $profile->json_data = $jsonData;
        $profile->cookie_data = $cookieData;
        $profile->group_id = $groupId;
        $profile->last_run_at = $lastRunAt;
        $profile->last_run_by = $lastRunBy;
        $profile->save();

        return ['success' => true, 'message' => 'OK', 'data' => null];
    }

    /**
     * Update profile status
     *
     * @param int $id
     * @param int $status
     * @param User $user
     * @return array
     */
    public function updateProfileStatus(int $id, int $status, User $user)
    {
        if (!$this->canAccessProfile($id, $user)) {
            return ['success' => false, 'message' => 'Không đủ quyền update trạng thái profile', 'data' => null];
        }

        $profile = Profile::find($id);
        if ($profile == null) {
            return ['success' => false, 'message' => 'Profile không tồn tại', 'data' => null];
        }

        $profile->status = $status;

        // If user run profile, update last run data
        if ($status == 2) {
            $profile->last_run_at = Carbon::now();
            $profile->last_run_by = $user->id;
        }

        $profile->save();

        return ['success' => true, 'message' => 'Thành công', 'data' => null];
    }

    /**
     * Delete profile
     *
     * @param int $id
     * @param User $user
     * @return array
     */
    public function deleteProfile(int $id, User $user)
    {
        if (!$this->canModifyProfile($id, $user)) {
            return ['success' => false, 'message' => 'Không đủ quyền xóa profile', 'data' => null];
        }

        $profile = Profile::find($id);
        if ($profile == null) {
            return ['success' => false, 'message' => 'Profile không tồn tại', 'data' => null];
        }

        $profileRoles = ProfileRole::where('profile_id', $id);
        $profileRoles->delete();
        $profile->delete();

        return ['success' => true, 'message' => 'Xóa thành công', 'data' => null];
    }

    /**
     * Get profile roles
     *
     * @param int $profileId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProfileRoles(int $profileId)
    {
        return ProfileRole::where('profile_id', $profileId)
                         ->with(['profile', 'user'])
                         ->get();
    }

    /**
     * Share profile with user
     *
     * @param int $profileId
     * @param int $userId
     * @param int $role
     * @param User $currentUser
     * @return array
     */
    public function shareProfile(int $profileId, int $userId, int $role, User $currentUser)
    {
        // Validate shared user
        $sharedUser = User::find($userId);
        if ($sharedUser == null) {
            return ['success' => false, 'message' => 'User ID không tồn tại', 'data' => null];
        }

        if ($sharedUser->role == 2) {
            return ['success' => false, 'message' => 'Không cần set quyền cho Admin', 'data' => null];
        }

        // Validate profile
        $profile = Profile::find($profileId);
        if ($profile == null) {
            return ['success' => false, 'message' => 'Profile không tồn tại', 'data' => null];
        }

        // Check permission
        if ($currentUser->role != 2 && $profile->created_by != $currentUser->id) {
            return ['success' => false, 'message' => 'Bạn phải là người tạo profile', 'data' => null];
        }

        // Handle profile role
        $profileRole = ProfileRole::where('profile_id', $profileId)
                                 ->where('user_id', $userId)
                                 ->first();

        // If role = 0, remove the role
        if ($role == 0) {
            if ($profileRole != null) {
                $profileRole->delete();
            }
            return ['success' => true, 'message' => 'OK', 'data' => null];
        }

        // Create or update role
        if ($profileRole == null) {
            $profileRole = new ProfileRole();
        }

        $profileRole->profile_id = $profileId;
        $profileRole->user_id = $userId;
        $profileRole->role = $role;
        $profileRole->save();

        return ['success' => true, 'message' => 'OK', 'data' => null];
    }

    /**
     * Get total profile count
     *
     * @return int
     */
    public function getTotalProfiles()
    {
        return Profile::count();
    }

    /**
     * Check if user can modify profile
     *
     * @param int $profileId
     * @param User $logonUser
     * @return bool
     */
    public function canModifyProfile(int $profileId, User $logonUser)
    {
        if ($logonUser->role >= 2) {
            return true; // Admin can modify all
        }

        $profileRole = ProfileRole::where('user_id', $logonUser->id)
                                 ->where('profile_id', $profileId)
                                 ->first();

        if ($profileRole != null && $profileRole->role == 2) {
            return true;
        }

        // Check group role
        $profile = Profile::find($profileId);
        if ($profile != null) {
            $groupRole = GroupRole::where('user_id', $logonUser->id)
                                 ->where('group_id', $profile->group_id)
                                 ->first();
            return $groupRole != null && $groupRole->role == 2;
        }

        return false;
    }

    /**
     * Check if user can access profile
     *
     * @param int $profileId
     * @param User $logonUser
     * @return bool
     */
    public function canAccessProfile(int $profileId, User $logonUser)
    {
        if ($logonUser->role >= 2) {
            return true; // Admin can access all
        }

        $profileRole = ProfileRole::where('user_id', $logonUser->id)
                                 ->where('profile_id', $profileId)
                                 ->first();

        if ($profileRole != null) {
            return true;
        }

        // Check group role
        $profile = Profile::find($profileId);
        if ($profile != null) {
            $groupRole = GroupRole::where('user_id', $logonUser->id)
                                 ->where('group_id', $profile->group_id)
                                 ->first();
            return $groupRole != null;
        }

        return false;
    }
}
