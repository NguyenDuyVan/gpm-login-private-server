<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WebAuthService
{
    /**
     * Authenticate admin user for web interface
     *
     * @param string $email
     * @param string $password
     * @return array
     */
    public function login(string $email, string $password)
    {
        // Find active user by email
        $user = User::where('email', $email)
            ->where('is_active', true)
            ->first();

        // Check password has not been hashed (Old version)
        if ($user != null && !$this->isHashed($user->password) && $user->password == $password) {
            $user->password = Hash::make($password);
            $user->save();
        }
        // Check if user exists and password is correct
        else if ($user == null || !Hash::check($password, $user->password)) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        // Only allow admin and moderator access to web interface
        if (!$user->hasModeratorAccess()) {
            return ['success' => false, 'message' => 'Access denied. Admin or Moderator privileges required.'];
        }

        Auth::login($user);
        return ['success' => true, 'message' => 'ok'];
    }

    function isHashed($password)
    {
        return Str::startsWith($password, '$2y$') && strlen($password) === 60;
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