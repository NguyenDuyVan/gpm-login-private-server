<?php

namespace App\Services;

use App\Models\User;
use App\Models\Profile;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use stdClass;

class AdminService
{
    /**
     * Get admin dashboard data
     *
     * @param User $loginUser
     * @return array
     */
    public function getDashboardData(User $loginUser)
    {
        $users = User::where('id', '<>', $loginUser->id)->orderBy('role', 'desc')->get();

        $storageType = 's3';
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

        $storageType = $setting->value;

        $s3Config = new stdClass();
        $s3Config->S3_KEY = env('S3_KEY');
        $s3Config->S3_PASSWORD = env('S3_PASSWORD');
        $s3Config->S3_BUCKET = env('S3_BUCKET');
        $s3Config->S3_REGION = env('S3_REGION');

        $cache_extension_setting = Setting::where('name', 'cache_extension')->first()->value ?? "off";

        return [
            'users' => $users,
            'storageType' => $storageType,
            's3Config' => $s3Config,
            'cache_extension_setting' => $cache_extension_setting
        ];
    }

    /**
     * Toggle user active status
     *
     * @param int $userId
     * @return bool
     */
    public function toggleUserActiveStatus(int $userId)
    {
        $user = User::find($userId);
        if ($user == null) {
            return false;
        }

        if ($user->active == 0) {
            $user->active = 1;
        } else if ($user->active == 1) {
            $user->active = 0;
        }

        $user->save();
        return true;
    }

    /**
     * Save system settings
     *
     * @param string $type
     * @param string|null $s3Key
     * @param string|null $s3Password
     * @param string|null $s3Bucket
     * @param string|null $s3Region
     * @param string $cacheExtension
     * @return string
     */
    public function saveSettings(string $type, ?string $s3Key = null, ?string $s3Password = null, ?string $s3Bucket = null, ?string $s3Region = null, string $cacheExtension = 'off')
    {
        // Save storage type setting
        $setting = Setting::where('name', 'storage_type')->first();
        if ($setting == null) {
            $setting = new Setting();
        }

        $setting->name = 'storage_type';
        $setting->value = $type;
        $setting->save();

        if ($setting->value == 'hosting') {
            Artisan::call('storage:link');
        } else if ($setting->value == 's3') {
            $this->setEnvironmentValue('S3_KEY', $s3Key);
            $this->setEnvironmentValue('S3_PASSWORD', $s3Password);
            $this->setEnvironmentValue('S3_BUCKET', $s3Bucket);
            $this->setEnvironmentValue('S3_REGION', $s3Region);
        }

        // Save cache extension setting
        $cache_extension_setting = Setting::where('name', 'cache_extension')->first();
        if ($cache_extension_setting == null) {
            $cache_extension_setting = new Setting();
        }
        $cache_extension_setting->name = 'cache_extension';
        $cache_extension_setting->value = $cacheExtension;
        $cache_extension_setting->save();

        return 'Storage type is changed to: ' . $setting->value;
    }

    /**
     * Reset all profile statuses to 1
     *
     * @return bool
     */
    public function resetProfileStatuses()
    {
        Profile::query()->update(['status' => 1]);
        return true;
    }

    /**
     * Run database migrations
     *
     * @return array
     */
    public function runMigrations()
    {
        try {
            \App\Http\Controllers\UpdateController::migrationDatabase();
            return ['success' => true, 'message' => 'Migration successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()];
        }
    }

    /**
     * Write environment value to .env file
     *
     * @param string $envKey
     * @param string $envValue
     */
    private function setEnvironmentValue($envKey, $envValue)
    {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);

        $oldValue = env($envKey);
        $str = str_replace("{$envKey}={$oldValue}", "{$envKey}={$envValue}", $str);
        $fp = fopen($envFile, 'w');
        fwrite($fp, $str);
        fclose($fp);
    }
}
