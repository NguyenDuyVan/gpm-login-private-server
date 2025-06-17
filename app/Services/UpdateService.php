<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use PclZip;
use Illuminate\Support\Facades\DB;

class UpdateService
{
    /**
     * Download and update from remote ZIP
     *
     * @param string $zipUrl
     * @return array
     */
    public function updateFromRemoteZip(string $zipUrl = 'https://github.com/ngochoaitn/gpm-login-private-server/releases/download/latest/latest-update.zip')
    {
        $zipFileName = 'update.zip';
        $zipFilePath = storage_path('app/' . $zipFileName);

        try {
            if (!$this->downloadFileFromUrl($zipUrl, $zipFilePath)) {
                return ['success' => false, 'message' => 'Cannot download ZIP file'];
            }

            $archive = new PclZip($zipFilePath);
            $destination = base_path();

            if ($archive->extract(PCLZIP_OPT_PATH, $destination) == 0) {
                return ['success' => false, 'message' => 'Failed to extract the ZIP file'];
            }

            Storage::delete($zipFileName);

            try {
                $this->migrationDatabase();
            } catch (\Exception $e) {
                return ['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()];
            }

            return [
                'success' => true,
                'message' => 'Update completed successfully: version ' . \App\Http\Controllers\Api\SettingController::$server_version
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Download file from URL
     *
     * @param string $url
     * @param string $fileName
     * @return bool
     */
    private function downloadFileFromUrl(string $url, string $fileName)
    {
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) coc_coc_browser/87.0.152 Chrome/81.0.4044.152 Safari/537.36\r\n" .
                    "Accept: */*\r\n" .
                    "Accept: */*\r\n" .
                    "Accept-Encoding: gzip, deflate, br\r\n"
            ],
            "https" => [
                "method" => "GET",
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) coc_coc_browser/87.0.152 Chrome/81.0.4044.152 Safari/537.36\r\n" .
                    "Accept: */*\r\n" .
                    "Accept: */*\r\n" .
                    "Accept-Encoding: gzip, deflate, br\r\n"
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $context = stream_context_create($opts);

        $content = @file_get_contents($url, false, $context);
        if ($content != false) {
            file_put_contents($fileName, $content);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Run database migration
     *
     * @return void
     * @throws \Exception
     */
    public function migrationDatabase()
    {
        try {
            $sql = "
            CREATE TABLE IF NOT EXISTS group_roles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                group_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                role INT COMMENT '1 - read only, 2 - full control',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES `users`(id) ON DELETE CASCADE
            );";

            DB::statement($sql);
        } catch (\Exception $e) {
            throw new \Exception('Migration failed: ' . $e->getMessage());
        }
    }
}
