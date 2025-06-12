<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\UploadService;
use App\Services\S3UploadService;

class UploadController extends BaseController
{
    protected $uploadService;
    protected $s3UploadService;

    public function __construct(UploadService $uploadService, S3UploadService $s3UploadService)
    {
        $this->uploadService = $uploadService;
        $this->s3UploadService = $s3UploadService;
    }

    public function store(Request $request)
    {
        if ($files = $request->file('file')) {
            $result = $this->uploadService->storeFile($request->file('file'), $request->file_name);
            return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
        }
        return $this->getJsonResponse(false, 'Thất bại', []);
    }

    public function delete(Request $request)
    {
        $result = $this->uploadService->deleteFile($request->file);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    /**
     * Generate S3 presigned URL for file upload
     *
     * @param Request $request
     * @return string JSON response
     */
    public function uploadS3(Request $request)
    {
        // Get optional parameters from request
        $fileName = $request->get('file_name');
        $maxFileSize = $request->get('max_file_size', 10485760); // Default 10MB
        $expires = $request->get('expires', '+10 minutes'); // Default 10 minutes

        // Generate presigned URL
        $result = $this->s3UploadService->generatePresignedUploadUrl($fileName, $maxFileSize, $expires);

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }
}
