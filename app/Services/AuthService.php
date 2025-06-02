<?php

namespace App\Services;

use App\Models\User;

class AuthService
{
    /**
     * Authenticate user and generate token
     *
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function login(string $userName, string $password)
    {
        $user = User::where('user_name', strtolower($userName))
            ->where('password', $password)
            ->where('active', '<>', 0)
            ->first();

        if ($user == null) {
            return ['success' => false, 'message' => 'Đăng nhập thất bại', 'data' => null];
        }

        // Remove all existing tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('token');
        $resp = ['token' => $token->plainTextToken];

        return ['success' => true, 'message' => 'Đăng nhập thành công', 'data' => $resp];
    }

    /**
     * Logout user by deleting all tokens
     *
     * @param User $user
     * @return array
     */
    public function logout(User $user)
    {
        $user->tokens()->delete();

        return ['success' => true, 'message' => 'Đăng xuất thành công', 'data' => null];
    }
}
