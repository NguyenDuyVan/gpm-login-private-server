<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupRole;
use App\Models\User;

class GroupService
{
    /**
     * Get all groups (excluding trash group with id = 0)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllGroups()
    {
        return Group::where('id', '!=', 0)->orderBy('sort')->get();
    }

    /**
     * Create a new group
     *
     * @param string $name
     * @param int $sort
     * @param int $userId
     * @return Group
     */
    public function createGroup(string $name, int $sort, int $userId)
    {
        $group = new Group();
        $group->name = $name;
        $group->sort = $sort;
        $group->created_by = $userId;
        $group->save();

        return $group;
    }

    /**
     * Update a group
     *
     * @param int $id
     * @param string $name
     * @param int $sort
     * @return Group|null
     */
    public function updateGroup(int $id, string $name, int $sort)
    {
        $group = Group::find($id);

        if ($group == null) {
            return null;
        }

        $group->name = $name;
        $group->sort = $sort;
        $group->save();

        return $group;
    }

    /**
     * Delete a group
     *
     * @param int $id
     * @return array
     */
    public function deleteGroup(int $id)
    {
        $group = Group::find($id);

        if ($group == null) {
            return ['success' => false, 'message' => 'Group không tồn tại!'];
        }

        if ($group->profiles->count() > 0) {
            return ['success' => false, 'message' => 'Không thể xóa Group có liên kết với Profiles!'];
        }

        $group->delete();

        return ['success' => true, 'message' => 'Xóa thành công'];
    }

    /**
     * Get total count of groups
     *
     * @return int
     */
    public function getTotalGroups()
    {
        return Group::count();
    }

    /**
     * Get group roles for a specific group
     *
     * @param int $groupId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getGroupRoles(int $groupId)
    {
        return GroupRole::where('group_id', $groupId)
                        ->with(['group', 'user'])
                        ->get();
    }

    /**
     * Share group with user
     *
     * @param int $groupId
     * @param int $userId
     * @param int $role
     * @param User $currentUser
     * @return array
     */
    public function shareGroup(int $groupId, int $userId, int $role, User $currentUser)
    {
        // Validate shared user
        $sharedUser = User::find($userId);
        if ($sharedUser == null) {
            return ['success' => false, 'message' => 'User ID không tồn tại'];
        }

        if ($sharedUser->role == 2) {
            return ['success' => false, 'message' => 'Không cần set quyền cho Admin'];
        }

        // Validate group
        $group = Group::find($groupId);
        if ($group == null) {
            return ['success' => false, 'message' => 'Profile không tồn tại'];
        }

        // Check permission
        if ($currentUser->role != 2 && $group->created_by != $currentUser->id) {
            return ['success' => false, 'message' => 'Bạn phải là người tạo group'];
        }

        // Handle group role
        $groupRole = GroupRole::where('group_id', $groupId)
                             ->where('user_id', $userId)
                             ->first();

        // If role = 0, remove the role
        if ($role == 0) {
            if ($groupRole != null) {
                $groupRole->delete();
            }
            return ['success' => true, 'message' => 'OK'];
        }

        // Create or update role
        if ($groupRole == null) {
            $groupRole = new GroupRole();
        }

        $groupRole->group_id = $groupId;
        $groupRole->user_id = $userId;
        $groupRole->role = $role;
        $groupRole->save();

        return ['success' => true, 'message' => 'OK'];
    }

    /**
     * Check if user has admin permission
     *
     * @param User $user
     * @return bool
     */
    public function hasAdminPermission(User $user)
    {
        return $user->role >= 2;
    }

    /**
     * Find group by ID
     *
     * @param int $id
     * @return Group|null
     */
    public function findGroup(int $id)
    {
        return Group::find($id);
    }
}
