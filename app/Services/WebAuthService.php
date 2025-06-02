<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class WebAuthService
{
    /**
     * Authenticate admin user
     *
     * @param string $username
     * @param string $password
     * @return array
     */
    public function login(string $username, string $password)
    {
        $user = User::where('role', 2)
                   ->where('user_name', $username)
                   ->where('password', $password)
                   ->where('active', '<>', 0)
                   ->first();

        if ($user == null) {
            return ['success' => false, 'message' => 'Login failed'];
        }

        Auth::login($user);
        return ['success' => true, 'message' => 'Login successful'];
    }

    /**
     * Logout user
     *
     * @return void
     */
    public function logout()
    {
        Auth::logout();
    }
}
