<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

trait CreatesApplication
{
    protected static $migrated = false;
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if (!self::$migrated) {
            Artisan::call('migrate:fresh', ['--seed' => true]);
            self::$migrated = true;
        }

        return $app;
    }
}
