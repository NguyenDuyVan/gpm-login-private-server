<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\GroupShare;
use App\Models\User;
use App\Services\GroupService;

class GroupController extends BaseController
{
    protected $groupService;

    public function __construct(GroupService $groupService)
    {
        $this->groupService = $groupService;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $groups = $this->groupService->getAllGroups();
        return $this->getJsonResponse(true, 'Thành công', $groups);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$this->groupService->hasAdminPermission($user))
            return $this->getJsonResponse(false, 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!', null);

        $group = $this->groupService->createGroup(
            $request->name,
            $request->order,
            $user->id
        );

        return $this->getJsonResponse(true, 'Thành công', $group);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!$this->groupService->hasAdminPermission($user))
            return $this->getJsonResponse(false, 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!', null);

        $group = $this->groupService->updateGroup(
            $id,
            $request->name,
            $request->order,
            $user->id
        );

        if ($group == null)
            return $this->getJsonResponse(false, 'Group không tồn tại', null);

        return $this->getJsonResponse(true, 'Cập nhật thành công', null);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();

        if (!$this->groupService->hasAdminPermission($user))
            return $this->getJsonResponse(false, 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!', null);

        $result = $this->groupService->deleteGroup($id);

        return $this->getJsonResponse($result['success'], $result['message'], null);
    }

    /**
     * Get total profile
     *
     * @return \Illuminate\Http\Response
     */
    public function getTotal()
    {
        $total = $this->groupService->getTotalGroups();
        return $this->getJsonResponse(true, 'OK', ['total' => $total]);
    }

    /**
     * Get list of users share
     */
    public function getGroupShares($id)
    {
        $groupShares = $this->groupService->getGroupShares($id);
        return $this->getJsonResponse(true, 'OK', $groupShares);
    }

    public function share($id, Request $request)
    {
        $user = $request->user();

        $result = $this->groupService->shareGroup(
            $id,
            $request->user_id,
            $request->role,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], null);
    }
}
