<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Group;
use App\Models\GroupShare;
use App\Models\ProfileShare;
use App\Models\User;
use App\Models\Tag;
use App\Services\TagService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProfileService
{
    protected $tagService;

    public function __construct(TagService $tagService)
    {
        $this->tagService = $tagService;
    }

    /**
     * Get profiles with filters and pagination
     *
     * @param User $user
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getProfiles(User $user, array $filters = [], array $extensiveFields = [])
    {
        $selectFields = ['id', 'name', 'storage_path', 'meta_data', 'group_id', 'created_by', 'status', 'last_run_at', 'last_run_by', 'created_at', 'updated_at'];
        // Add extensive fields if provided, avoid duplicates
        if (count($extensiveFields) > 0) {
            foreach ($extensiveFields as $field) {
                if (!in_array($field, $selectFields)) {
                    $selectFields[] = $field;
                }
            }
        }

        // Default, show all active profiles (not soft deleted)
        $query = Profile::active()
            ->select($selectFields)
            ->with(['creator', 'lastRunUser', 'group']);

        // If user isn't admin, show by permissions
        if (!$user->isAdmin()) {
            $groupShareIds = DB::table('group_shares')->where('user_id', $user->id)->pluck('group_id');

            $query = Profile::active()
                ->select($selectFields)
                ->whereIn('id', function ($subQuery) use ($user, $groupShareIds) {
                    $subQuery->select('profiles.id')
                        ->from('profiles')
                        ->join('profile_shares', 'profiles.id', '=', 'profile_shares.profile_id')
                        ->where(function ($q) use ($user, $groupShareIds) {
                            $q->where('profile_shares.user_id', $user->id)
                                ->orWhereIn('profiles.group_id', $groupShareIds)
                                ->orWhere('profiles.created_by', $user->id);
                        });
                })
                ->with(['creator', 'lastRunUser', 'group']);
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
        if (isset($filters['group_id']) && $filters['group_id'] != Group::where('name', 'All')->first()?->id) {
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
            $query->whereHas('tags', function ($q) use ($tags) {
                $q->whereIn('name', $tags);
            });
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
     * @param string $storagePath
     * @param array $jsonData
     * @param array|null $metaData
     * @param int $groupId
     * @param int $userId
     * @param string $storageType
     * @return Profile
     */
    public function createProfile(string $name, string $storagePath, array $jsonData, ?array $metaData, int $groupId, int $userId, string $storageType = Profile::STORAGE_S3)
    {
        $profile = new Profile();
        $profile->name = $name;
        $profile->storage_type = $storageType;
        $profile->storage_path = $storagePath;
        $profile->json_data = $jsonData;
        $profile->meta_data = $metaData ?? [];
        $profile->group_id = $groupId;
        $profile->created_by = $userId;
        $profile->status = Profile::STATUS_READY;
        $profile->usage_count = 0;
        $profile->save();

        // Create profile share for creator with FULL access
        $profileShare = new ProfileShare();
        $profileShare->profile_id = $profile->id;
        $profileShare->user_id = $userId;
        $profileShare->role = ProfileShare::ROLE_FULL;
        $profileShare->save();

        return Profile::where('id', $profile->id)->with(['creator', 'lastRunUser', 'group'])->first();
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

        $profile = Profile::active()->find($id);
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
     * @param string $storagePath
     * @param array $jsonData
     * @param array $metaData
     * @param int $groupId
     * @param string|null $lastRunAt
     * @param int|null $lastRunBy
     * @param User $user
     * @return array
     */
    public function updateProfile(int $id, string $name, string $storagePath, array $jsonData, array $metaData, int $groupId, ?string $lastRunAt, ?int $lastRunBy, User $user)
    {
        if (!$this->canModifyProfile($id, $user)) {
            return ['success' => false, 'message' => 'Không đủ quyền sửa profile', 'data' => null];
        }

        $profile = Profile::active()->find($id);
        if ($profile == null) {
            return ['success' => false, 'message' => 'Profile không tồn tại', 'data' => null];
        }

        $profile->name = $name;
        $profile->storage_path = $storagePath;
        $profile->json_data = $jsonData;
        $profile->meta_data = $metaData;
        $profile->group_id = $groupId;
        $profile->last_run_at = $lastRunAt;
        $profile->last_run_by = $lastRunBy;
        $profile->save();

        return ['success' => true, 'message' => 'OK', 'data' => null];
    }

    /**
     * Update profile status and track usage
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

        $profile = Profile::active()->find($id);
        if ($profile == null) {
            return ['success' => false, 'message' => 'Profile không tồn tại', 'data' => null];
        }

        // If user starts using profile
        if ($status == Profile::STATUS_IN_USE) {
            $profile->markAsInUse($user);
            $profile->recordUsage($user);
        } else if ($status == Profile::STATUS_READY) {
            $profile->markAsReady();
        } else {
            $profile->status = $status;
            $profile->save();
        }

        return ['success' => true, 'message' => 'Thành công', 'data' => null];
    }

    /**
     * Soft delete profile
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

        $profile = Profile::active()->find($id);
        if ($profile == null) {
            return ['success' => false, 'message' => 'Profile không tồn tại', 'data' => null];
        }

        // Soft delete the profile
        $profile->softDelete($user);

        return ['success' => true, 'message' => 'Xóa thành công', 'data' => null];
    }

    /**
     * Restore soft deleted profile
     *
     * @param int $id
     * @param User $user
     * @return array
     */
    public function restoreProfile(int $id, User $user)
    {
        if (!$user->isAdmin() && !$user->isModerator()) {
            return ['success' => false, 'message' => 'Không đủ quyền khôi phục profile', 'data' => null];
        }

        $profile = Profile::deleted()->find($id);
        if ($profile == null) {
            return ['success' => false, 'message' => 'Profile không tồn tại trong thùng rác', 'data' => null];
        }

        $profile->restore();

        return ['success' => true, 'message' => 'Khôi phục thành công', 'data' => null];
    }

    /**
     * Get profile shares
     *
     * @param int $profileId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProfileShares(int $profileId)
    {
        return ProfileShare::where('profile_id', $profileId)
            ->with(['profile', 'user'])
            ->get();
    }

    /**
     * Share profile with user
     *
     * @param int $profileId
     * @param int $userId
     * @param string $role
     * @param User $currentUser
     * @return array
     */
    public function shareProfile(int $profileId, int $userId, string $role, User $currentUser)
    {
        // Validate shared user
        $sharedUser = User::find($userId);
        if ($sharedUser == null) {
            return ['success' => false, 'message' => 'User ID không tồn tại', 'data' => null];
        }

        if ($sharedUser->isAdmin()) {
            return ['success' => false, 'message' => 'Không cần set quyền cho Admin', 'data' => null];
        }

        // Validate profile
        $profile = Profile::active()->find($profileId);
        if ($profile == null) {
            return ['success' => false, 'message' => 'Profile không tồn tại', 'data' => null];
        }

        // Check permission
        if (!$currentUser->isAdmin() && $profile->created_by != $currentUser->id) {
            return ['success' => false, 'message' => 'Bạn phải là người tạo profile', 'data' => null];
        }

        // Handle profile share
        $profileShare = ProfileShare::where('profile_id', $profileId)
            ->where('user_id', $userId)
            ->first();

        // If role is empty or invalid, remove the share
        if (empty($role) || !in_array($role, [ProfileShare::ROLE_FULL, ProfileShare::ROLE_EDIT, ProfileShare::ROLE_VIEW])) {
            if ($profileShare != null) {
                $profileShare->delete();
            }
            return ['success' => true, 'message' => 'OK', 'data' => null];
        }

        // Create or update share
        if ($profileShare == null) {
            $profileShare = new ProfileShare();
        }

        $profileShare->profile_id = $profileId;
        $profileShare->user_id = $userId;
        $profileShare->role = $role;
        $profileShare->save();

        return ['success' => true, 'message' => 'OK', 'data' => null];
    }

    /**
     * Get total profile count
     *
     * @return int
     */
    public function getTotalProfiles()
    {
        return Profile::active()->count();
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
        if ($logonUser->isAdmin()) {
            return true; // Admin can modify all
        }

        $profile = Profile::active()->find($profileId);
        if (!$profile) {
            return false;
        }

        // Check if user is the creator
        if ($profile->created_by == $logonUser->id) {
            return true;
        }

        // Check profile shares with FULL access
        $profileShare = ProfileShare::where('user_id', $logonUser->id)
            ->where('profile_id', $profileId)
            ->where('role', ProfileShare::ROLE_FULL)
            ->first();

        if ($profileShare != null) {
            return true;
        }

        // Check group shares with FULL access
        if ($profile->group) {
            $groupShare = GroupShare::where('user_id', $logonUser->id)
                ->where('group_id', $profile->group_id)
                ->where('role', GroupShare::ROLE_FULL)
                ->first();
            if ($groupShare != null) {
                return true;
            }
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
        if ($logonUser->isAdmin()) {
            return true; // Admin can access all
        }

        $profile = Profile::active()->find($profileId);
        if (!$profile) {
            return false;
        }

        // Check if user is the creator
        if ($profile->created_by == $logonUser->id) {
            return true;
        }

        // Check profile shares
        $profileShare = ProfileShare::where('user_id', $logonUser->id)
            ->where('profile_id', $profileId)
            ->first();

        if ($profileShare != null) {
            return true;
        }

        // Check group shares
        if ($profile->group) {
            $groupShare = GroupShare::where('user_id', $logonUser->id)
                ->where('group_id', $profile->group_id)
                ->first();
            if ($groupShare != null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Legacy method for backward compatibility - Get profile roles
     * This method maps to the new profile shares system
     *
     * @param int $profileId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProfileRoles(int $profileId)
    {
        return $this->getProfileShares($profileId);
    }

    /**
     * Start using profile
     *
     * @param int $profileId
     * @param int $userId
     * @return array
     */
    public function startUsingProfile(int $profileId, int $userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User không tồn tại', 'data' => null];
        }

        if (!$this->canAccessProfile($profileId, $user)) {
            return ['success' => false, 'message' => 'Không đủ quyền sử dụng profile', 'data' => null];
        }

        $profile = Profile::active()->find($profileId);
        if (!$profile) {
            return ['success' => false, 'message' => 'Profile không tồn tại', 'data' => null];
        }

        // Check if profile is already in use by someone else
        if ($profile->isInUse() && $profile->using_by != $userId) {
            return ['success' => false, 'message' => 'Profile đang được sử dụng bởi người khác', 'data' => null];
        }

        // Mark profile as in use
        $profile->markAsInUse($user);
        $profile->recordUsage($user);

        return ['success' => true, 'message' => 'Bắt đầu sử dụng profile thành công', 'data' => null];
    }

    /**
     * Stop using profile
     *
     * @param int $profileId
     * @param int $userId
     * @return array
     */
    public function stopUsingProfile(int $profileId, int $userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User không tồn tại', 'data' => null];
        }

        if (!$this->canAccessProfile($profileId, $user)) {
            return ['success' => false, 'message' => 'Không đủ quyền với profile', 'data' => null];
        }

        $profile = Profile::active()->find($profileId);
        if (!$profile) {
            return ['success' => false, 'message' => 'Profile không tồn tại', 'data' => null];
        }

        // Only allow user to stop using if they are the current user
        if ($profile->using_by != $userId) {
            return ['success' => false, 'message' => 'Bạn không phải người đang sử dụng profile này', 'data' => null];
        }

        // Mark profile as ready
        $profile->markAsReady();

        return ['success' => true, 'message' => 'Dừng sử dụng profile thành công', 'data' => null];
    }

    /**
     * Add tags to profile
     *
     * @param int $profileId
     * @param array $tagNames
     * @param User $user
     * @return array
     */
    public function addTagsToProfile(int $profileId, array $tagNames, User $user)
    {
        try {
            if (!$this->canModifyProfile($profileId, $user)) {
                return ['success' => false, 'message' => 'Không đủ quyền thêm tag cho profile', 'data' => null];
            }

            $profile = Profile::active()->find($profileId);
            if (!$profile) {
                return ['success' => false, 'message' => 'Profile không tồn tại', 'data' => null];
            }

            if (empty($tagNames)) {
                return ['success' => false, 'message' => 'Danh sách tag không được để trống', 'data' => null];
            }

            // Find or create tags
            $tags = $this->tagService->findOrCreateTags($tagNames, $user->id);
            $tagIds = collect($tags)->pluck('id')->toArray();

            // Attach tags to profile (avoid duplicates)
            $profile->tags()->syncWithoutDetaching($tagIds);

            return [
                'success' => true,
                'message' => 'Thêm tag thành công',
                'data' => $profile->load(['tags', 'creator', 'group'])
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Remove tags from profile
     *
     * @param int $profileId
     * @param array $tagIds
     * @param User $user
     * @return array
     */
    public function removeTagsFromProfile(int $profileId, array $tagIds, User $user)
    {
        try {
            if (!$this->canModifyProfile($profileId, $user)) {
                return ['success' => false, 'message' => 'Không đủ quyền xóa tag khỏi profile', 'data' => null];
            }

            $profile = Profile::active()->find($profileId);
            if (!$profile) {
                return ['success' => false, 'message' => 'Profile không tồn tại', 'data' => null];
            }

            if (empty($tagIds)) {
                return ['success' => false, 'message' => 'Danh sách tag ID không được để trống', 'data' => null];
            }

            // Remove tags from profile
            $profile->tags()->detach($tagIds);

            return [
                'success' => true,
                'message' => 'Xóa tag thành công',
                'data' => $profile->load(['tags', 'creator', 'group'])
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
}
