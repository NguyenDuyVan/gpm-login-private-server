<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class UploadService
{
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
                $storedFile = $file->storeAs('public/profiles', $fileName);

                // Extract the filename from the path
                $fileName = str_replace("public/profiles/", "", $storedFile);

                return [
                    'success' => true,
                    'message' => 'Thành công',
                    'data' => [
                        'path' => 'storage/profiles',
                        'file_name' => $fileName
                    ]
                ];
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
     * Delete file from storage
     *
     * @param string $fileName
     * @return array
     */
    public function deleteFile(string $fileName)
    {
        try {
            $fullLocation = 'public/profiles/' . $fileName;
            Storage::delete($fullLocation);

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
