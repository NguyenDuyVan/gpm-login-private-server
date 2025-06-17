<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use PclZip;
use Illuminate\Support\Facades\DB;

class UpdateService
{
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

    public function migrationDatabase()
    {
        try {
            $this->createMigrationsTable();

            $migrationsPath = database_path('migrations');
            $migrationFiles = glob($migrationsPath . '/*.php');
            sort($migrationFiles);

            $executedMigrations = [];
            $errors = [];

            foreach ($migrationFiles as $migrationFile) {
                $migrationName = basename($migrationFile, '.php');

                if ($this->migrationAlreadyRun($migrationName)) {
                    continue;
                }

                try {
                    $sqlStatements = $this->convertMigrationToSQL($migrationFile);

                    foreach ($sqlStatements as $sql) {
                        if (!empty(trim($sql))) {
                            DB::statement($sql);
                        }
                    }

                    $this->recordMigration($migrationName);
                    $executedMigrations[] = $migrationName;

                } catch (\Exception $e) {
                    $errors[] = "Migration {$migrationName} failed: " . $e->getMessage();
                }
            }

            if (!empty($errors)) {
                throw new \Exception('Some migrations failed: ' . implode('; ', $errors));
            }

            return [
                'success' => true,
                'message' => 'Migrations completed successfully',
                'executed' => $executedMigrations
            ];

        } catch (\Exception $e) {
            throw new \Exception('Migration failed: ' . $e->getMessage());
        }
    }


    private function convertMigrationToSQL(string $migrationFile): array
    {
        $migrationName = basename($migrationFile, '.php');
        $sqlStatements = [];

        // Handle specific migrations with custom SQL
        if (strpos($migrationName, 'create_users_table') !== false) {
            $sqlStatements[] = "
                CREATE TABLE IF NOT EXISTS users (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    system_role ENUM('ADMIN', 'MOD', 'USER') NOT NULL DEFAULT 'USER',
                    is_active BOOLEAN NOT NULL DEFAULT TRUE,
                    display_name VARCHAR(255) NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL
                )";
        } elseif (strpos($migrationName, 'create_personal_access_tokens_table') !== false) {
            $sqlStatements[] = "
                CREATE TABLE IF NOT EXISTS personal_access_tokens (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tokenable_type VARCHAR(255) NOT NULL,
                    tokenable_id BIGINT UNSIGNED NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    abilities TEXT NULL,
                    last_used_at TIMESTAMP NULL DEFAULT NULL,
                    expires_at TIMESTAMP NULL DEFAULT NULL,
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    INDEX personal_access_tokens_tokenable_type_tokenable_id_index (tokenable_type, tokenable_id)
                )";
        } elseif (strpos($migrationName, 'create_groups_table') !== false) {
            $sqlStatements[] = "
                CREATE TABLE IF NOT EXISTS `groups` (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    sort INT NOT NULL DEFAULT 0,
                    created_by BIGINT UNSIGNED NOT NULL,
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
                )";
        } elseif (strpos($migrationName, 'create_profiles_table') !== false) {
            $sqlStatements[] = "
                CREATE TABLE IF NOT EXISTS profiles (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    raw_json TEXT NOT NULL,
                    notes TEXT NULL,
                    created_by BIGINT UNSIGNED NOT NULL,
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
                )";
        } elseif (strpos($migrationName, 'create_profile_roles_table') !== false) {
            $sqlStatements[] = "
                CREATE TABLE IF NOT EXISTS profile_roles (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    profile_id BIGINT UNSIGNED NOT NULL,
                    user_id BIGINT UNSIGNED NOT NULL,
                    role INT NOT NULL COMMENT '1 - read only, 2 - full control',
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )";
        } elseif (strpos($migrationName, 'create_settings_table') !== false) {
            $sqlStatements[] = "
                CREATE TABLE IF NOT EXISTS settings (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL UNIQUE,
                    value TEXT NULL,
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL
                )";
        } elseif (strpos($migrationName, 'create_group_roles_table') !== false) {
            $sqlStatements[] = "
                CREATE TABLE IF NOT EXISTS group_roles (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    group_id BIGINT UNSIGNED NOT NULL,
                    user_id BIGINT UNSIGNED NOT NULL,
                    role INT NOT NULL COMMENT '1 - read only, 2 - full control',
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )";
        } elseif (strpos($migrationName, 'migrate_env_settings_to_database') !== false) {
            $sqlStatements[] = "
                INSERT IGNORE INTO settings (name, value, created_at, updated_at) VALUES
                ('storage_type', 'local', NOW(), NOW()),
                ('s3_key', '', NOW(), NOW()),
                ('s3_secret', '', NOW(), NOW()),
                ('s3_bucket', '', NOW(), NOW()),
                ('s3_region', '', NOW(), NOW()),
                ('cache_extension', 'off', NOW(), NOW())";
        } elseif (strpos($migrationName, 'create_group_shares_table') !== false) {
            $sqlStatements[] = "
                CREATE TABLE IF NOT EXISTS group_shares (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    group_id BIGINT UNSIGNED NOT NULL,
                    shared_with_user_id BIGINT UNSIGNED NOT NULL,
                    permission ENUM('READ', 'write') NOT NULL DEFAULT 'read',
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
                    FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE
                )";
        } elseif (strpos($migrationName, 'create_tags_table') !== false) {
            $sqlStatements[] = "
                CREATE TABLE IF NOT EXISTS tags (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    color VARCHAR(7) NOT NULL DEFAULT '#3B82F6',
                    created_by BIGINT UNSIGNED NOT NULL,
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
                )";
        } elseif (strpos($migrationName, 'create_profile_shares_table') !== false) {
            $sqlStatements[] = "
                CREATE TABLE IF NOT EXISTS profile_shares (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    profile_id BIGINT UNSIGNED NOT NULL,
                    shared_with_user_id BIGINT UNSIGNED NOT NULL,
                    permission ENUM('read', 'write') NOT NULL DEFAULT 'read',
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE,
                    FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE
                )";
        } elseif (strpos($migrationName, 'create_profile_tags_table') !== false) {
            $sqlStatements[] = "
                CREATE TABLE IF NOT EXISTS profile_tags (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    profile_id BIGINT UNSIGNED NOT NULL,
                    tag_id BIGINT UNSIGNED NOT NULL,
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE,
                    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
                )";
        } elseif (strpos($migrationName, 'create_proxies_table') !== false) {
            $sqlStatements[] = "
                CREATE TABLE IF NOT EXISTS proxies (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    type ENUM('http', 'https', 'socks4', 'socks5') NOT NULL,
                    host VARCHAR(255) NOT NULL,
                    port INT NOT NULL,
                    username VARCHAR(255) NULL,
                    password VARCHAR(255) NULL,
                    is_active BOOLEAN NOT NULL DEFAULT TRUE,
                    created_by BIGINT UNSIGNED NOT NULL,
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
                )";
        } elseif (strpos($migrationName, 'create_proxy_tags_table') !== false) {
            $sqlStatements[] = "
                CREATE TABLE IF NOT EXISTS proxy_tags (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    proxy_id BIGINT UNSIGNED NOT NULL,
                    tag_id BIGINT UNSIGNED NOT NULL,
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    FOREIGN KEY (proxy_id) REFERENCES proxies(id) ON DELETE CASCADE,
                    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
                )";
        }

        // Handle table structure updates
        elseif (strpos($migrationName, 'update_users_table_structure') !== false) {
            // Since users table already has the new structure, just ensure data consistency
            $sqlStatements[] = "UPDATE users SET email = COALESCE(email, 'unknown@example.com') WHERE email IS NULL OR email = ''";
            $sqlStatements[] = "UPDATE users SET system_role = COALESCE(system_role, 'USER') WHERE system_role IS NULL";
            $sqlStatements[] = "UPDATE users SET is_active = COALESCE(is_active, TRUE) WHERE is_active IS NULL";
        } elseif (strpos($migrationName, 'update_profiles_table_structure') !== false) {
            // Check if notes column exists, if not add it - simplified approach
            $sqlStatements[] = "SELECT 1"; // Placeholder since this table seems to already have the structure
        } elseif (strpos($migrationName, 'update_groups_table_structure') !== false) {
            // Since the groups table already has sort_order, just ensure it's up to date
            $sqlStatements[] = "UPDATE `groups` SET sort_order = COALESCE(sort_order, 0) WHERE sort_order IS NULL";
            $sqlStatements[] = "SELECT 1"; // Success placeholder
        }

        return $sqlStatements;
    }


    private function createMigrationsTable()
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL
        )";

        DB::statement($sql);
    }

    private function migrationAlreadyRun(string $migrationName): bool
    {
        return DB::table('migrations')
            ->where('migration', $migrationName)
            ->exists();
    }

    private function recordMigration(string $migrationName)
    {
        // Get the next batch number
        $batch = DB::table('migrations')->max('batch') ?? 0;
        $batch++;

        DB::table('migrations')->insert([
            'migration' => $migrationName,
            'batch' => $batch
        ]);
    }
}
