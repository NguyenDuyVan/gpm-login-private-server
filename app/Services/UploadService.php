<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class UploadService
{
    protected $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }
    /**
     * Store uploaded file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $fileName
     * @return array
     */
    public function storeFile($file, string $fileName)
    {
        try {
            if ($file->getSize() > 0) {
                // Initialize settings if needed
                $this->settingService->initializeDefaultSettings();

                // Get storage type from database
                $storageType = $this->settingService->getSetting('storage_type')->value ?? 'local';

                if ($storageType === 's3') {
                    return $this->storeFileToS3($file, $fileName);
                } else {
                    return $this->storeFileLocally($file, $fileName);
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'Thất bại',
                    'data' => ['message' => 'File rỗng']
                ];
            }
        } catch (\Exception $ex) {
            return [
                'success' => false,
                'message' => 'Thất bại',
                'data' => $ex
            ];
        }
    }

    /**
     * Store file locally
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $fileName
     * @return array
     */
    private function storeFileLocally($file, string $fileName)
    {
        $storedFile = $file->storeAs('public/profiles', $fileName);
        $fileName = str_replace("public/profiles/", "", $storedFile);

        return [
            'success' => true,
            'message' => 'Thành công',
            'data' => [
                'path' => 'storage/profiles',
                'file_name' => $fileName
            ]
        ];
    }

    /**
     * Store file to S3
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $fileName
     * @return array
     */
    private function storeFileToS3($file, string $fileName)
    {
        // Configure S3 from database settings
        $this->configureS3FromDatabase();

        $storedFile = $file->storeAs('profiles', $fileName, 's3');

        return [
            'success' => true,
            'message' => 'Thành công',
            'data' => [
                'path' => 'profiles',
                'file_name' => $fileName
            ]
        ];
    }

    /**
     * Configure S3 from database settings
     *
     * @return void
     */
    private function configureS3FromDatabase()
    {
        $s3Key = $this->settingService->getSetting('s3_key')->value ?? '';
        $s3Secret = $this->settingService->getSetting('s3_secret')->value ?? '';
        $s3Bucket = $this->settingService->getSetting('s3_bucket')->value ?? '';
        $s3Region = $this->settingService->getSetting('s3_region')->value ?? '';

        config(['filesystems.disks.s3.key' => $s3Key]);
        config(['filesystems.disks.s3.secret' => $s3Secret]);
        config(['filesystems.disks.s3.bucket' => $s3Bucket]);
        config(['filesystems.disks.s3.region' => $s3Region]);
    }

    /**
     * Delete file from storage
     *
     * @param string $fileName
     * @return array
     */
    public function deleteFile(string $fileName)
    {
        try {
            // Initialize settings if needed
            $this->settingService->initializeDefaultSettings();

            // Get storage type from database
            $storageType = $this->settingService->getSetting('storage_type')->value ?? 'local';

            if ($storageType === 's3') {
                $this->configureS3FromDatabase();
                $fullLocation = 'profiles/' . $fileName;
                Storage::disk('s3')->delete($fullLocation);
            } else {
                $fullLocation = 'public/profiles/' . $fileName;
                Storage::delete($fullLocation);
            }

            return [
                'success' => true,
                'message' => 'Thành công',
                'data' => []
            ];
        } catch (\Exception $ex) {
            return [
                'success' => false,
                'message' => 'Thất bại',
                'data' => $ex
            ];
        }
    }
}
