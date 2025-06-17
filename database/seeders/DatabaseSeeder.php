<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Group;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            SettingsSeeder::class,
        ]);

        // \App\Models\User::factory(10)->create();

        User::factory()->create([
            'email' => 'Administrator',
            'display_name' => 'Administrator',
            'password' => bcrypt('Administrator'),
            'system_role' => 'ADMIN',
            'is_active' => true
        ]);

        Group::factory(5)->create();
    }
}
