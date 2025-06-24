<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;

class UserApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $adminUser;
    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'is_admin' => 1,
            'is_active' => 1,
            'system_role' => 'ADMIN'
        ]);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'is_admin' => 0,
            'is_active' => 1,
            'system_role' => 'USER'
        ]);
    }

    /** @test */
    public function it_can_register_new_user()
    {
        $userData = [
            'email' => 'newuser@test.com',
            'display_name' => 'New User',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/users/register', $userData);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'email',
                    'display_name',
                    'is_active',
                    'system_role'
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@test.com',
            'display_name' => 'New User'
        ]);
    }

    /** @test */
    public function it_can_register_with_username()
    {
        $userData = [
            'user_name' => 'newuser@test.com',
            'display_name' => 'New User',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/users/register', $userData);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@test.com',
            'display_name' => 'New User'
        ]);
    }

    /** @test */
    public function admin_can_get_users_list()
    {
        Sanctum::actingAs($this->adminUser);

        User::factory()->count(3)->create();

        $response = $this->getJson('/api/users/');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'email',
                            'display_name',
                            'is_active',
                            'system_role',
                            'created_at',
                            'updated_at'
                        ]
                    ],
                    'current_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    /** @test */
    public function regular_user_can_get_users_list()
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/users/');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_get_current_user()
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/users/current-user');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'id' => $this->regularUser->id,
                    'email' => $this->regularUser->email,
                    'display_name' => $this->regularUser->display_name
                ]
            ]);
    }

    /** @test */
    public function it_can_update_user_display_name()
    {
        Sanctum::actingAs($this->regularUser);

        $updateData = [
            'display_name' => 'Updated Display Name'
        ];

        $response = $this->postJson('/api/users/update', $updateData);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'display_name' => 'Updated Display Name'
        ]);
    }

    /** @test */
    public function it_can_update_user_password()
    {
        Sanctum::actingAs($this->regularUser);

        $updateData = [
            'new_password' => 'newpassword123'
        ];

        $response = $this->postJson('/api/users/update', $updateData);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify password was updated
        $user = User::find($this->regularUser->id);
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    /** @test */
    public function admin_can_update_user_system_role()
    {
        Sanctum::actingAs($this->adminUser);

        $updateData = [
            'system_role' => 'ADMIN'
        ];

        $response = $this->postJson('/api/users/update', $updateData);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $this->adminUser->id,
            'system_role' => 'ADMIN'
        ]);
    }

    /** @test */
    public function it_can_update_multiple_fields()
    {
        Sanctum::actingAs($this->regularUser);

        $updateData = [
            'display_name' => 'New Display Name',
            'new_password' => 'newpassword123'
        ];

        $response = $this->postJson('/api/users/update', $updateData);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $user = User::find($this->regularUser->id);

        $this->assertEquals('New Display Name', $user->display_name);
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    /** @test */
    public function it_can_search_users()
    {
        Sanctum::actingAs($this->adminUser);

        User::factory()->create([
            'email' => 'test1@example.com',
            'display_name' => 'Test User 1'
        ]);

        User::factory()->create([
            'email' => 'test2@example.com',
            'display_name' => 'Test User 2'
        ]);

        User::factory()->create([
            'email' => 'different@example.com',
            'display_name' => 'Different User'
        ]);

        $response = $this->getJson('/api/users/?search=test');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_paginate_users()
    {
        Sanctum::actingAs($this->adminUser);

        User::factory()->count(50)->create();

        $response = $this->getJson('/api/users/?per_page=10&page=2');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'current_page',
                    'per_page',
                    'total',
                    'data'
                ]
            ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_users_list()
    {
        $response = $this->getJson('/api/users/');

        $response->assertStatus(401);
    }

    /** @test */
    public function unauthenticated_user_cannot_get_current_user()
    {
        $response = $this->getJson('/api/users/current-user');

        $response->assertStatus(401);
    }

    /** @test */
    public function unauthenticated_user_cannot_update_profile()
    {
        $response = $this->postJson('/api/users/update', [
            'display_name' => 'Updated Name'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_user_registration_data()
    {
        // Test missing email
        $response = $this->postJson('/api/users/register', [
            'display_name' => 'Test User',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => false]);

        // Test missing password
        $response = $this->postJson('/api/users/register', [
            'email' => 'test@example.com',
            'display_name' => 'Test User'
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function it_prevents_duplicate_email_registration()
    {
        // Create a user first
        User::factory()->create(['email' => 'existing@test.com']);

        $userData = [
            'email' => 'existing@test.com',
            'display_name' => 'Duplicate User',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/users/register', $userData);

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function it_creates_user_with_default_values()
    {
        $userData = [
            'email' => 'default@test.com',
            'display_name' => 'Default User',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/users/register', $userData);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'email' => 'default@test.com',
            'is_active' => 1,
            'system_role' => 'USER'
        ]);
    }

    /** @test */
    public function update_without_changes_returns_success()
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->postJson('/api/users/update', []);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_maintains_user_timestamps()
    {
        Sanctum::actingAs($this->regularUser);

        $originalUpdatedAt = $this->regularUser->updated_at;

        $this->travel(1)->minute();

        $response = $this->postJson('/api/users/update', [
            'display_name' => 'Updated Name'
        ]);

        $response->assertStatus(200);

        $user = User::find($this->regularUser->id);
        $this->assertNotEquals($originalUpdatedAt, $user->updated_at);
    }
}
