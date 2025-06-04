<?php

namespace App\Services;

use Aws\S3\S3Client;
use Aws\S3\PostObjectV4;
use Exception;

class S3UploadService
{
    protected $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * Generate S3 presigned URL for file upload
     *
     * @param string|null $fileName
     * @param int $maxFileSize Maximum file size in bytes (default: 10MB)
     * @param string $expires Expiration time (default: +10 minutes)
     * @return array
     */
    public function generatePresignedUploadUrl($fileName = null, $maxFileSize = 10485760, $expires = '+10 minutes')
    {
        try {
            // Initialize settings if needed
            $this->settingService->initializeDefaultSettings();

            // Get S3 settings from database
            $s3Settings = $this->settingService->getS3Settings();

            if (!$s3Settings['success']) {
                return [
                    'success' => false,
                    'message' => 'S3 configuration not found or incomplete',
                    'data' => null
                ];
            }

            $s3Data = $s3Settings['data'];

            // Validate S3 configuration
            if (empty($s3Data['s3_api_key']) || empty($s3Data['s3_api_secret']) ||
                empty($s3Data['s3_api_bucket']) || empty($s3Data['s3_api_region'])) {
                return [
                    'success' => false,
                    'message' => 'S3 configuration is incomplete. Please check your S3 settings.',
                    'data' => null
                ];
            }

            // Create S3 client
            $s3 = new S3Client([
                'version' => 'latest',
                'region' => $s3Data['s3_api_region'],
                'credentials' => [
                    'key' => $s3Data['s3_api_key'],
                    'secret' => $s3Data['s3_api_secret'],
                ],
            ]);

            $bucket = $s3Data['s3_api_bucket'];

            // Generate unique file key if not provided
            if (!$fileName) {
                $fileName = uniqid() . '.jpg';
            }

            $key = 'uploads/' . $fileName;

            $formInputs = ['key' => $key];
            $options = [
                ['acl' => 'private'],
                ['bucket' => $bucket],
                ['starts-with', '$key', 'uploads/'],
                ['content-length-range', 0, $maxFileSize] // File size limit
            ];

            $postObject = new PostObjectV4(
                $s3,
                $bucket,
                $formInputs,
                $options,
                $expires
            );

            $response = [
                'url' => $postObject->getFormAttributes()['action'],
                'fields' => $postObject->getFormInputs(),
                'file_key' => $key,
                'bucket' => $bucket,
                'region' => $s3Data['s3_api_region']
            ];

            return [
                'success' => true,
                'message' => 'Presigned URL generated successfully',
                'data' => $response
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate presigned URL: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
}
