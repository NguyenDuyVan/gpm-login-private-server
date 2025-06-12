<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Group;
use App\Models\User;

class SettingService
{
    public static $server_version = 12;

    /**
     * Initialize default settings if they don't exist
     *
     * @return void
     */
    public function initializeDefaultSettings()
    {
        $defaultSettings = [
            'storage_type' => 'local',
            's3_key' => '',
            's3_secret' => '',
            's3_bucket' => '',
            's3_region' => '',
            'cache_extension' => 'off'
        ];

        foreach ($defaultSettings as $key => $value) {
            $setting = Setting::where('name', $key)->first();
            if (!$setting) {
                $this->setSetting($key, $value);
            }
        }
    }

    /**
     * Get S3 settings from database
     *
     * @return array
     */
    public function getS3Settings()
    {
        try {
            $this->initializeDefaultSettings();

            $apiKey = $this->getSetting('s3_key')?->value ?? '';
            $apiSecret = $this->getSetting('s3_secret')?->value ?? '';
            $apiBucket = $this->getSetting('s3_bucket')?->value ?? '';
            $apiRegion = $this->getSetting('s3_region')?->value ?? '';

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
     * Get S3 configuration object for admin form
     *
     * @return object
     */
    public function getS3Config()
    {
        $this->initializeDefaultSettings();

        return (object) [
            'S3_KEY' => $this->getSetting('s3_key')?->value ?? '',
            'S3_PASSWORD' => $this->getSetting('s3_secret')?->value ?? '',
            'S3_BUCKET' => $this->getSetting('s3_bucket')?->value ?? '',
            'S3_REGION' => $this->getSetting('s3_region')?->value ?? ''
        ];
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
     * Get a setting by name
     *
     * @param string $key
     * @return Setting|null
     */
    public function getSetting(string $key)
    {
        return Setting::where('name', $key)->first();
    }

    /**
     * Get a setting value by name (convenience method)
     *
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public function get(string $key, $default = null)
    {
        $setting = $this->getSetting($key);
        return $setting ? $setting->value : $default;
    }

    /**
     * Update S3 settings
     *
     * @param array $s3Data
     * @return array
     */
    public function updateS3Settings(array $s3Data)
    {
        try {
            $this->setSetting('s3_key', $s3Data['S3_KEY'] ?? '');
            $this->setSetting('s3_secret', $s3Data['S3_PASSWORD'] ?? '');
            $this->setSetting('s3_bucket', $s3Data['S3_BUCKET'] ?? '');
            $this->setSetting('s3_region', $s3Data['S3_REGION'] ?? '');

            return ['success' => true, 'message' => 'Cập nhật S3 settings thành công'];
        } catch (\Exception $ex) {
            return ['success' => false, 'message' => 'Lỗi khi cập nhật S3 settings: ' . $ex->getMessage()];
        }
    }

    /**
     * Get storage type setting
     *
     * @return array
     */
    public function getStorageTypeSetting()
    {
        $this->initializeDefaultSettings();

        $setting = Setting::where('name', 'storage_type')->first();
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
        $this->initializeDefaultSettings();

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

        // Get settings from database
        $storage_type = Setting::where('name', 'storage_type')->first();
        $cache_extension = Setting::where('name', 'cache_extension')->first();

        $response['version'] = $version;
        $response['storage_type'] = $storage_type->value ?? 'local';
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
}
