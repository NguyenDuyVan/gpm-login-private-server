<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupShare;
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
        return Group::where('id', '!=', 0)->orderBy('sort_order')->get();
    }

    /**
     * Create a new group
     *
     * @param string $name
     * @param int $order
     * @param int $userId
     * @return Group
     */
    public function createGroup(string $name, int $order, int $userId)
    {
        $group = new Group();
        $group->name = $name;
        $group->sort_order = $order;
        $group->created_by = $userId;
        $group->save();

        return $group;
    }

    /**
     * Update a group
     *
     * @param int $id
     * @param string $name
     * @param int $order
     * @param int $updatedBy
     * @return Group|null
     */
    public function updateGroup(int $id, string $name, int $order, int $updatedBy)
    {
        $group = Group::find($id);

        if ($group == null) {
            return null;
        }

        $group->name = $name;
        $group->sort_order = $order;
        $group->updated_by = $updatedBy;
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
     * Get group shares for a specific group
     *
     * @param int $groupId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getGroupShares(int $groupId)
    {
        return GroupShare::where('group_id', $groupId)
                        ->with(['group', 'user'])
                        ->get();
    }

    /**
     * Share group with user
     *
     * @param int $groupId
     * @param int $userId
     * @param string $role
     * @param User $currentUser
     * @return array
     */
    public function shareGroup(int $groupId, int $userId, string $role, User $currentUser)
    {
        // Validate shared user
        $sharedUser = User::find($userId);
        if ($sharedUser == null) {
            return ['success' => false, 'message' => 'User ID không tồn tại'];
        }

        if ($sharedUser->isAdmin()) {
            return ['success' => false, 'message' => 'Không cần set quyền cho Admin'];
        }

        // Validate group
        $group = Group::find($groupId);
        if ($group == null) {
            return ['success' => false, 'message' => 'Group không tồn tại'];
        }

        // Check permission
        if (!$currentUser->isAdmin() && $group->created_by != $currentUser->id) {
            return ['success' => false, 'message' => 'Bạn phải là người tạo group'];
        }

        // Handle group share
        $groupShare = GroupShare::where('group_id', $groupId)
                               ->where('user_id', $userId)
                               ->first();

        // If role is empty or invalid, remove the share
        if (empty($role) || !in_array($role, [GroupShare::ROLE_FULL, GroupShare::ROLE_EDIT, GroupShare::ROLE_VIEW])) {
            if ($groupShare != null) {
                $groupShare->delete();
            }
            return ['success' => true, 'message' => 'OK'];
        }

        // Create or update share
        if ($groupShare == null) {
            $groupShare = new GroupShare();
        }

        $groupShare->group_id = $groupId;
        $groupShare->user_id = $userId;
        $groupShare->role = $role;
        $groupShare->save();

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
        return $user->isAdmin();
    }

    /**
     * Check if user can access group
     *
     * @param int $groupId
     * @param User $user
     * @return bool
     */
    public function canAccessGroup(int $groupId, User $user)
    {
        if ($user->isAdmin()) {
            return true;
        }

        $group = Group::find($groupId);
        if (!$group) {
            return false;
        }

        // Check if user is the creator
        if ($group->created_by == $user->id) {
            return true;
        }

        // Check group shares
        $groupShare = GroupShare::where('group_id', $groupId)
                               ->where('user_id', $user->id)
                               ->first();

        return $groupShare !== null;
    }

    /**
     * Check if user can modify group
     *
     * @param int $groupId
     * @param User $user
     * @return bool
     */
    public function canModifyGroup(int $groupId, User $user)
    {
        if ($user->isAdmin()) {
            return true;
        }

        $group = Group::find($groupId);
        if (!$group) {
            return false;
        }

        // Check if user is the creator
        if ($group->created_by == $user->id) {
            return true;
        }

        // Check group shares with FULL access
        $groupShare = GroupShare::where('group_id', $groupId)
                               ->where('user_id', $user->id)
                               ->where('role', GroupShare::ROLE_FULL)
                               ->first();

        return $groupShare !== null;
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
