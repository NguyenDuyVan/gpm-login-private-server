<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Services\GroupService;

class GroupController extends BaseController
{
    protected $groupService;

    public function __construct(GroupService $groupService)
    {
        $this->groupService = $groupService;
    }

    public function index(Request $request)
    {
        $groups = $this->groupService->getAllGroups();
        return response()->json([
            'success' => true,
            'message' => 'Thành công',
            'data' => $groups
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$this->groupService->hasAdminPermission($user)) {
            return response()->json([
                'success' => true,
                'message' => 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!',
                'data' => null
            ]);
        }

        $group = $this->groupService->createGroup(
            $request->name,
            $request->order,
            $user->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Thành công',
            'data' => $group
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!$this->groupService->hasAdminPermission($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!',
                'data' => null
            ]);
        }

        $group = $this->groupService->updateGroup(
            $id,
            $request->name,
            $request->order,
            $user->id
        );

        if ($group == null) {
            return response()->json([
                'success' => false,
                'message' => 'Group không tồn tại',
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thành công',
            'data' => null
        ]);
    }

    public function destroy($id, Request $request)
    {
        $user = $request->user();

        if (!$this->groupService->hasAdminPermission($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!',
                'data' => null
            ]);
        }

        $result = $this->groupService->deleteGroup($id);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => null
        ]);
    }


    public function getTotal()
    {
        $total = $this->groupService->getTotalGroups();
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => ['total' => $total]
        ]);
    }


    public function getGroupShares($id)
    {
        $groupShares = $this->groupService->getGroupShares($id);
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $groupShares
        ]);
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

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => null
        ]);
    }
}
