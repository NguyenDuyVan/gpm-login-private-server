<?php

namespace App\Services;

use Aws\S3\S3Client;
use Aws\S3\PostObjectV4;
use Exception;
use Illuminate\Support\Facades\Storage;

class S3UploadService
{
    protected $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function getS3RegionCode($s3Region)
    {
        if($s3Region == 'AFSouth1') return 'af-south-1';
        if($s3Region == 'APEast1') return 'ap-east-1';
        if($s3Region == 'APNortheast1') return 'ap-northeast-1';
        if($s3Region == 'APNortheast2') return 'ap-northeast-2';
        if($s3Region == 'APNortheast3') return 'ap-northeast-3';
        if($s3Region == 'APSouth1') return 'ap-south-1';
        if($s3Region == 'APSoutheast1') return 'ap-southeast-1';
        if($s3Region == 'APSoutheast2') return 'ap-southeast-2';
        if($s3Region == 'CACentral1') return 'ca-central-1';
        if($s3Region == 'CNNorth1') return 'cn-north-1';
        if($s3Region == 'CNNorthWest1') return 'cn-northwest-1';
        if($s3Region == 'EUCentral1') return 'eu-central-1';
        if($s3Region == 'EUNorth1') return 'eu-north-1';
        if($s3Region == 'EUSouth1') return 'eu-south-1';
        if($s3Region == 'EUWest1') return 'eu-west-1';
        if($s3Region == 'EUWest2') return 'eu-west-2';
        if($s3Region == 'EUWest3') return 'eu-west-3';
        if($s3Region == 'MESouth1') return 'me-south-1';
        if($s3Region == 'SAEast1') return 'sa-east-1';
        if($s3Region == 'USEast1') return 'us-east-1';
        if($s3Region == 'USEast2') return 'us-east-2';
        if($s3Region == 'USGovCloudEast1') return 'us-gov-east-1';
        if($s3Region == 'USGovCloudWest1') return 'us-gov-west-1';
        if($s3Region == 'USIsobEast1') return 'us-isob-east-1';
        if($s3Region == 'USIsoEast1') return 'us-iso-east-1';
        if($s3Region == 'USWest1') return 'us-west-1';
        if($s3Region == 'USWest2') return 'us-west-2';
        return 'us-east-1';
    }

    public function generateUploadPresignedUrl($fileName, $expires = '+10 minutes', $mimeType = 'application/octet-stream')
    {
        // Nhận kiểu MIME từ client
        // $mimeType = 'application/octet-stream';

        // Tạo tên file duy nhất
        // Key S3
        $key = 'profiles/' . $fileName;

        // Tạo S3Client
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
        $regionCode = $this->getS3RegionCode($s3Data['s3_api_region']);
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => $regionCode,
            'credentials' => [
                'key' => $s3Data['s3_api_key'],
                'secret' => $s3Data['s3_api_secret'],
            ],
        ]);

        $bucket = $s3Data['s3_api_bucket'];

        $options = [
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $mimeType,
            'ACL' => 'public-read' // nếu bạn muốn file public sau khi upload
        ];

        // Tạo URL upload (presigned PUT)
        $command = $s3->getCommand('PutObject', $options);

        // $expires = '+10 minutes';

        $request = $s3->createPresignedRequest($command, $expires);

        $presignedUrl = (string) $request->getUri();

        // Trả về thông tin cho frontend
        return [
            'success' => true,
            'message' => 'Presigned URL generated successfully',
            'data' => [
                'upload_url' => $presignedUrl,              // Dùng để PUT file
                'public_url' => "https://{$bucket}.s3.amazonaws.com/{$key}", // URL truy cập sau khi upload
                'key' => $key,                               // Đường dẫn file trong bucket
                // 'expires_in' => 600,                         // 10 phút
                'mime_type' => $mimeType,
                'method' => 'PUT'
            ]
        ];
    }

    /**
     * Generate S3 presigned URL for file upload
     *
     * @param string|null $fileName
     * @param int $maxFileSize Maximum file size in bytes (default: 10MB)
     * @param string $expires Expiration time (default: +10 minutes)
     * @return array
     */
    public function generatePresignedUploadUrl($fileName = null, $maxFileSize = 10485760, $expires = '+10 minutes', $mimeType = 'application/octet-stream')
    {
        try {
            return $this->generateUploadPresignedUrl($fileName, $expires, $mimeType);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate presigned URL: ' . $e->getMessage(),
                'data' => null
            ];
        }

        // try {
        //     // Initialize settings if needed
        //     $this->settingService->initializeDefaultSettings();

        //     // Get S3 settings from database
        //     $s3Settings = $this->settingService->getS3Settings();

        //     if (!$s3Settings['success']) {
        //         return [
        //             'success' => false,
        //             'message' => 'S3 configuration not found or incomplete',
        //             'data' => null
        //         ];
        //     }

        //     $s3Data = $s3Settings['data'];

        //     // Validate S3 configuration
        //     if (empty($s3Data['s3_api_key']) || empty($s3Data['s3_api_secret']) ||
        //         empty($s3Data['s3_api_bucket']) || empty($s3Data['s3_api_region'])) {
        //         return [
        //             'success' => false,
        //             'message' => 'S3 configuration is incomplete. Please check your S3 settings.',
        //             'data' => null
        //         ];
        //     }

        //     // Create S3 client
        //     $regionCode = $this->getS3RegionCode($s3Data['s3_api_region']);
        //     $s3 = new S3Client([
        //         'version' => 'latest',
        //         'region' => $regionCode,
        //         'credentials' => [
        //             'key' => $s3Data['s3_api_key'],
        //             'secret' => $s3Data['s3_api_secret'],
        //         ],
        //     ]);

        //     $bucket = $s3Data['s3_api_bucket'];

        //     // Generate unique file key if not provided
        //     if (!$fileName) {
        //         $fileName = uniqid() . '.jpg';
        //     }

        //     $key = 'uploads/' . $fileName;

        //     $formInputs = [
        //         'key' => $key,
        //         'acl' => 'public-read'
        //     ];
        //     $options = [
        //         ['acl' => 'public-read'],
        //         ['bucket' => $bucket],
        //         ['starts-with', '$key', 'uploads/'],
        //         ['content-length-range', 0, $maxFileSize] // File size limit
        //     ];

        //     $postObject = new PostObjectV4(
        //         $s3,
        //         $bucket,
        //         $formInputs,
        //         $options,
        //         $expires
        //     );

        //     $response = [
        //         'url' => $postObject->getFormAttributes()['action'],
        //         'fields' => $postObject->getFormInputs(),
        //         'file_key' => $key,
        //         'bucket' => $bucket,
        //         'region' => $regionCode
        //     ];

        //     return [
        //         'success' => true,
        //         'message' => 'Presigned URL generated successfully',
        //         'data' => $response
        //     ];

        // } catch (Exception $e) {
        //     return [
        //         'success' => false,
        //         'message' => 'Failed to generate presigned URL: ' . $e->getMessage(),
        //         'data' => null
        //     ];
        // }
    }
}
