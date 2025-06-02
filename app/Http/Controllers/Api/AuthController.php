<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\AuthService;

class AuthController extends BaseController
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request)
    {
        $result = $this->authService->login(
            $request->user_name,
            $request->password
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }
}
