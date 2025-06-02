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

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $result = $this->profileService->createProfile(
            $request->name,
            $request->s3_path,
            $request->json_data,
            $request->cookie_data,
            $request->group_id,
            $user->id
        );

        return $this->getJsonResponse(true, 'Thành công', $result);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Request $request)
    {
        $user = $request->user();
        $result = $this->profileService->getProfile($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
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

        $result = $this->profileService->updateProfile(
            $id,
            $request->name,
            $request->s3_path,
            $request->json_data,
            $request->cookie_data,
            $request->group_id,
            $request->last_run_at,
            $request->last_run_by,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    /**
     * Update status
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
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


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();

        $result = $this->profileService->deleteProfile($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    /**
     * Get list of users role
     */
    public function getProfileRoles($id)
    {
        $profileRoles = $this->profileService->getProfileRoles($id);
        return $this->getJsonResponse(true, 'OK', $profileRoles);
    }    /**
     * Share profile
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
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

    /**
     * Get total profile
     *
     * @return \Illuminate\Http\Response
     */
    public function getTotal()
    {
        $total = $this->profileService->getTotalProfiles();
        return $this->getJsonResponse(true, 'OK', ['total' => $total]);
    }

}
