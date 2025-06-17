<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->words(3, true),
            'storage_type' => fake()->randomElement(['S3', 'GOOGLE_DRIVE', 'LOCAL']),
            'storage_path' => '/' . fake()->word() . '/' . fake()->word(),
            'json_data' => [
                'browser' => 'chrome',
                'version' => fake()->randomElement(['118.0.0.0', '119.0.0.0', '120.0.0.0']),
                'plugins' => fake()->randomElements(['flash', 'java', 'silverlight'], 2)
            ],
            'meta_data' => [
                'cookies' => fake()->randomElements(['session=abc123', 'auth=xyz789'], 1)
            ],
            'group_id' => Group::factory(),
            'created_by' => User::factory(),
            'status' => Profile::STATUS_READY,
            'usage_count' => fake()->numberBetween(0, 100),
            'is_deleted' => false,
        ];
    }

    /**
     * Indicate that the profile is in use.
     */
    public function inUse(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Profile::STATUS_IN_USE,
            'using_by' => User::factory(),
            'last_used_at' => now(),
        ]);
    }

    /**
     * Indicate that the profile is soft deleted.
     */
    public function deleted(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_deleted' => true,
            'deleted_at' => now(),
            'deleted_by' => User::factory(),
        ]);
    }
}
