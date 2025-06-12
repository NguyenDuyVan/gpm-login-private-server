<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\Group;
use App\Models\User;
use App\Services\SettingService;

class SettingController extends BaseController
{
    public static $server_version = 12;
    protected $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * Get s3 setting
     *
     * @return string
     */
    public function getS3Setting(Request $request)
    {
        $result = $this->settingService->getS3Settings();
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function getStorageTypeSetting()
    {
        $result = $this->settingService->getStorageTypeSetting();
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    // 23.7.2024 check version of private server
    public function getPrivateServerVersion()
    {
        $result = $this->settingService->getPrivateServerVersion();
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    // 24.9.2024
    public function getAllSetting()
    {
        $result = $this->settingService->getAllSettings();
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }
}
