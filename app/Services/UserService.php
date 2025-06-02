<?php

namespace App\Services;

use App\Models\User;

class UserService
{
    /**
     * Get all users ordered by role and username
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllUsers()
    {
        return User::orderBy('role', 'desc')->orderBy('user_name')->get();
    }

    /**
     * Create a new user
     *
     * @param string $userName
     * @param string $displayName
     * @param string $password
     * @return array
     */
    public function createUser(string $userName, string $displayName, string $password)
    {
        // Check if username already exists
        if (User::where('user_name', strtolower($userName))->count() > 0) {
            return ['success' => false, 'message' => 'Tên người dùng đã tồn tại', 'data' => null];
        }

        $user = new User();
        $user->user_name = strtolower($userName);
        $user->display_name = $displayName;
        $user->password = $password;
        $user->role = 1;
        $user->active = 0;
        $user->save();

        return ['success' => true, 'message' => 'Đăng kí thành công', 'data' => $user];
    }

    /**
     * Update user information
     *
     * @param int $userId
     * @param string $displayName
     * @param int|null $role
     * @param string|null $newPassword
     * @return array
     */
    public function updateUser(int $userId, string $displayName, ?int $role = null, ?string $newPassword = null)
    {
        $user = User::find($userId);

        if (!$user) {
            return ['success' => false, 'message' => 'User không tồn tại', 'data' => null];
        }

        $user->display_name = $displayName;

        if ($role !== null) {
            $user->role = $role;
        }

        if ($newPassword !== null) {
            $user->password = $newPassword;
        }

        $user->save();

        return ['success' => true, 'message' => 'Đổi thông tin thành công', 'data' => $user];
    }

    /**
     * Get user by ID
     *
     * @param int $userId
     * @return User|null
     */
    public function getUserById(int $userId)
    {
        return User::find($userId);
    }
}
