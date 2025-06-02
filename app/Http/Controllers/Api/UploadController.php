<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\UploadService;

class UploadController extends BaseController
{
    protected $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
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
}
