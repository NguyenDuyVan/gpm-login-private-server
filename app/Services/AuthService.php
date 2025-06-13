<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    /**
     * Authenticate user and generate token
     *
     * @param string $email
     * @param string $password
     * @return array
     */
    public function login(string $email, string $password)
    {
        $user = User::where('email', $email)
            ->where('is_active', true)
            ->first();

        if ($user != null && !$this->isHashed($user->password) && $user->password == $password) {
            $user->password = Hash::make($password);
            $user->save();
        } else if ($user == null || !Hash::check($password, $user->password)) {
            return ['success' => false, 'message' => 'Đăng nhập thất bại', 'data' => null];
        }

        // Remove all existing tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('token');
        $resp = ['token' => $token->plainTextToken];

        return ['success' => true, 'message' => 'Đăng nhập thành công', 'data' => $resp];
    }

    function isHashed($password)
    {
        return Str::startsWith($password, '$2y$') && strlen($password) === 60;
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
