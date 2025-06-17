<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Services\ProfileService;

class ProfileController extends BaseController
{
    protected $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $filters = [
            'group_id' => $request->group_id ?? null,
            'search' => $request->search ?? null,
            'share_mode' => $request->share_mode ?? null,
            'tags' => $request->tags ?? null,
            'sort' => $request->sort ?? null,
            'per_page' => $request->per_page ?? 30
        ];

        $extensiveFields = $request->extensive_fields ?? [];
        if ($extensiveFields && is_string($extensiveFields)) {
            $extensiveFields = array_map('trim', explode(',', $extensiveFields));
        }

        $profiles = $this->profileService->getProfiles($user, $filters, $extensiveFields);
        return $this->getJsonResponse(true, 'OK', $profiles);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $result = $this->profileService->createProfile(
            $request->name,
            $request->storage_path,
            $request->json_data,
            $request->cookie_data,
            $request->group_id,
            $user->id,
            $request->storage_type ?? 'S3'
        );

        return $this->getJsonResponse(true, 'Thành công', $result);
    }

    public function show($id, Request $request)
    {
        $user = $request->user();
        $result = $this->profileService->getProfile($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        $result = $this->profileService->updateProfile(
            $id,
            $request->name,
            $request->storage_path,
            $request->json_data,
            $request->meta_data,
            $request->group_id,
            null,
            null,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function updateStatus($id, Request $request)
    {
        $user = $request->user();

        $result = $this->profileService->updateProfileStatus(
            $id,
            $request->status,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function destroy($id, Request $request)
    {
        $user = $request->user();

        $result = $this->profileService->deleteProfile($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function getProfileShares($id)
    {
        $profileShares = $this->profileService->getProfileShares($id);
        return $this->getJsonResponse(true, 'OK', $profileShares);
    }

    public function share($id, Request $request)
    {
        $user = $request->user();

        $result = $this->profileService->shareProfile(
            $id,
            $request->user_id,
            $request->role,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function getTotal()
    {
        $total = $this->profileService->getTotalProfiles();
        return $this->getJsonResponse(true, 'OK', ['total' => $total]);
    }

    public function startUsing($id, Request $request)
    {
        $user = $request->user();
        $result = $this->profileService->startUsingProfile($id, $user->id);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function stopUsing($id, Request $request)
    {
        $user = $request->user();
        $result = $this->profileService->stopUsingProfile($id, $user->id);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function addTags($id, Request $request)
    {
        $user = $request->user();
        $result = $this->profileService->addTagsToProfile($id, $request->tags, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function removeTags($id, Request $request)
    {
        $user = $request->user();
        $result = $this->profileService->removeTagsFromProfile($id, $request->tags, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function restore($id, Request $request)
    {
        $user = $request->user();
        $result = $this->profileService->restoreProfile($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }
}
