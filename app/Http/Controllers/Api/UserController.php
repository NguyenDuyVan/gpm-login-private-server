<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\UserService;

class UserController extends BaseController
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $users = $this->userService->getAllUsers();
        return $this->getJsonResponse(true, 'Thành công', $users);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $result = $this->userService->createUser(
            $request->email ?? $request->user_name,
            $request->display_name,
            $request->password
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $result = $this->userService->updateUser(
            $request->user()->id,
            $request->display_name,
            $request->system_role ?? null,
            $request->new_password ?? null
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    /**
     * Get current user
     */
    public function getCurrentUser(Request $request){
        $user = $request->user();
        return $this->getJsonResponse(true, 'OK', $user);
    }
}
