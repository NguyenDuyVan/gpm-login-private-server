<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Group;
use App\Models\User;

class SettingService
{
    public static $server_version = 12;

    /**
     * Get S3 settings from environment
     *
     * @return array
     */
    public function getS3Settings()
    {
        try {
            $apiKey = env('S3_KEY');
            $apiSecret = env('S3_PASSWORD');
            $apiBucket = env('S3_BUCKET');
            $apiRegion = env('S3_REGION');

            $settings = [
                's3_api_key' => $apiKey,
                's3_api_secret' => $apiSecret,
                's3_api_bucket' => $apiBucket,
                's3_api_region' => $apiRegion
            ];

            return ['success' => true, 'message' => 'OK', 'data' => $settings];
        } catch (\Exception $ex) {
            return ['success' => false, 'message' => 'Chưa cài đặt đủ thông tin S3 API', 'data' => null];
        }
    }

    /**
     * Set or update a setting
     *
     * @param string $key
     * @param string $value
     * @return Setting
     */
    public function setSetting(string $key, string $value)
    {
        $setting = Setting::where('name', $key)->first();

        if ($setting == null) {
            $setting = new Setting();
            $setting->name = $key;
        }

        $setting->value = $value;
        $setting->save();

        return $setting;
    }

    /**
     * Get storage type setting
     *
     * @return array
     */
    public function getStorageTypeSetting()
    {
        $setting = Setting::where('name', 'storage_type')->first();

        // Create setting if not exists based on .env file
        if ($setting == null) {
            $setting = new Setting();
            $setting->name = 'storage_type';

            $apiKey = env('S3_KEY');
            $apiSecret = env('S3_PASSWORD');
            $apiBucket = env('S3_BUCKET');
            $apiRegion = env('S3_REGION');

            if ($apiKey != null && $apiSecret != null && $apiBucket != null && $apiRegion != null) {
                $setting->value = 's3';
            } else {
                $setting->value = 'hosting';
            }
            $setting->save();
        }

        return ['success' => true, 'message' => 'OK', 'data' => $setting->value];
    }

    /**
     * Get private server version and ensure trash group exists
     *
     * @return array
     */
    public function getPrivateServerVersion()
    {
        $version = self::$server_version;
        $response = [];

        // Check trash group for version 11+
        if ($version >= 11) {
            $result = $this->ensureTrashGroupExists();
            if (!$result['success']) {
                $version -= 1;
                $response['message'] = $result['message'];
            }
        }

        $response['version'] = $version;
        return ['success' => true, 'message' => 'OK', 'data' => $response];
    }

    /**
     * Get all settings
     *
     * @return array
     */
    public function getAllSettings()
    {
        $version = self::$server_version;
        $response = [];

        // Check trash group for version 11+
        if ($version >= 11) {
            $result = $this->ensureTrashGroupExists();
            if (!$result['success']) {
                $version -= 1;
                $response['message'] = $result['message'];
            }
        }

        // Get storage type setting
        $storage_type = Setting::where('name', 'storage_type')->first();
        $cache_extension = Setting::where('name', 'cache_extension')->first();

        if ($storage_type == null) {
            $storage_type = new Setting();
            $storage_type->name = 'storage_type';

            $apiKey = env('S3_KEY');
            $apiSecret = env('S3_PASSWORD');
            $apiBucket = env('S3_BUCKET');
            $apiRegion = env('S3_REGION');

            if ($apiKey != null && $apiSecret != null && $apiBucket != null && $apiRegion != null) {
                $storage_type->value = 's3';
            } else {
                $storage_type->value = 'hosting';
            }
            $storage_type->save();
        }

        $response['version'] = $version;
        $response['storage_type'] = $storage_type->value ?? 'hosting';
        $response['cache_extension'] = $cache_extension->value ?? 'off';

        return ['success' => true, 'message' => 'OK', 'data' => $response];
    }

    /**
     * Ensure trash group exists for version 11+
     *
     * @return array
     */
    private function ensureTrashGroupExists()
    {
        $groupTrashId = 0;
        $groupTrashName = 'Trash auto create (update private server version 11)';

        if (!Group::where('id', $groupTrashId)->exists()) {
            try {
                $userAdmin = User::where('role', 2)->first();
                if (!$userAdmin) {
                    return ['success' => false, 'message' => 'No admin user found'];
                }

                $group = new Group();
                $group->name = $groupTrashName;
                $group->sort = 2147483647; // int max
                $group->created_by = $userAdmin->id;
                $group->save();
            } catch (\Exception $e) {
                return ['success' => false, 'message' => 'Can not create Trash group'];
            }
        }

        $group = Group::where('name', $groupTrashName)->first();

        if ($group == null) {
            return ['success' => false, 'message' => 'Trash group not found'];
        }

        if ($group->id != $groupTrashId) {
            try {
                $group->id = $groupTrashId;
                $group->save();
            } catch (\Exception $e) {
                return ['success' => false, 'message' => 'Can not update id group Trash'];
            }
        }

        return ['success' => true, 'message' => 'Trash group ensured'];
    }

    /**
     * Get setting by name
     *
     * @param string $name
     * @return Setting|null
     */
    public function getSetting(string $name)
    {
        return Setting::where('name', $name)->first();
    }
}
