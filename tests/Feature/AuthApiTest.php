<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $activeUser;
    protected $inactiveUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create active user
        $this->activeUser = User::factory()->create([
            'email' => 'active@test.com',
            'password' => Hash::make('password123'),
            'is_admin' => 0,
            'is_active' => 1,
            'system_role' => 'USER'
        ]);

        // Create inactive user
        $this->inactiveUser = User::factory()->create([
            'email' => 'inactive@test.com',
            'password' => Hash::make('password123'),
            'is_admin' => 0,
            'is_active' => 0,
            'system_role' => 'USER'
        ]);
    }

    /** @test */
    public function it_can_login_with_email()
    {
        $loginData = [
            'email' => 'active@test.com',
            'password' => 'password123'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'email',
                        'display_name',
                        'is_active',
                        'system_role'
                    ],
                    'token'
                ]
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    /** @test */
    public function it_can_login_with_username()
    {
        $loginData = [
            'user_name' => 'active@test.com',
            'password' => 'password123'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'email',
                        'display_name',
                        'is_active',
                        'system_role'
                    ],
                    'token'
                ]
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    /** @test */
    public function it_fails_login_with_wrong_password()
    {
        $loginData = [
            'email' => 'active@test.com',
            'password' => 'wrongpassword'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'invalid_credentials'
            ]);

        $this->assertNull($response->json('data'));
    }

    /** @test */
    public function it_fails_login_with_nonexistent_email()
    {
        $loginData = [
            'email' => 'nonexistent@test.com',
            'password' => 'password123'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'user_not_found'
            ]);
    }

    /** @test */
    public function it_fails_login_with_inactive_user()
    {
        $loginData = [
            'email' => 'inactive@test.com',
            'password' => 'password123'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'user_inactive'
            ]);
    }

    /** @test */
    public function it_fails_login_without_email_or_username()
    {
        $loginData = [
            'password' => 'password123'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'email_required'
            ]);
    }

    /** @test */
    public function it_fails_login_without_password()
    {
        $loginData = [
            'email' => 'active@test.com'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'password_required'
            ]);
    }

    /** @test */
    public function it_fails_login_with_empty_credentials()
    {
        $response = $this->getJson('/api/users/login');

        $response->assertStatus(200)
            ->assertJson([
                'success' => false
            ]);
    }

    /** @test */
    public function login_creates_valid_token()
    {
        $loginData = [
            'email' => 'active@test.com',
            'password' => 'password123'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);
        $this->assertIsString($token);

        // Verify token works for authenticated requests
        $authResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/users/current-user');

        $authResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function successful_login_returns_user_data()
    {
        $loginData = [
            'email' => 'active@test.com',
            'password' => 'password123'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $userData = $response->json('data.user');

        $this->assertEquals($this->activeUser->id, $userData['id']);
        $this->assertEquals($this->activeUser->email, $userData['email']);
        $this->assertEquals($this->activeUser->display_name, $userData['display_name']);
        $this->assertEquals($this->activeUser->is_active, $userData['is_active']);
        $this->assertEquals($this->activeUser->system_role, $userData['system_role']);
    }

    /** @test */
    public function admin_user_can_login()
    {
        $adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('adminpass'),
            'is_admin' => 1,
            'is_active' => 1,
            'system_role' => 'ADMIN'
        ]);

        $loginData = [
            'email' => 'admin@test.com',
            'password' => 'adminpass'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $userData = $response->json('data.user');
        $this->assertEquals('ADMIN', $userData['system_role']);
        $this->assertEquals(1, $userData['is_active']);
    }

    /** @test */
    public function login_with_case_insensitive_email()
    {
        $loginData = [
            'email' => 'ACTIVE@TEST.COM',
            'password' => 'password123'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function login_trims_whitespace_from_email()
    {
        $loginData = [
            'email' => '  active@test.com  ',
            'password' => 'password123'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function login_with_special_characters_in_password()
    {
        $specialUser = User::factory()->create([
            'email' => 'special@test.com',
            'password' => Hash::make('p@ssw0rd!@#$%^&*()'),
            'is_active' => 1
        ]);

        $loginData = [
            'email' => 'special@test.com',
            'password' => 'p@ssw0rd!@#$%^&*()'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function login_prevents_sql_injection()
    {
        $loginData = [
            'email' => "admin@test.com'; DROP TABLE users; --",
            'password' => 'password123'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson(['success' => false]);

        // Verify users table still exists by checking if our test user still exists
        $this->assertDatabaseHas('users', [
            'email' => 'active@test.com'
        ]);
    }

    /** @test */
    public function login_handles_very_long_email()
    {
        $longEmail = str_repeat('a', 300) . '@test.com';

        $loginData = [
            'email' => $longEmail,
            'password' => 'password123'
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function login_handles_very_long_password()
    {
        $longPassword = str_repeat('a', 1000);

        $loginData = [
            'email' => 'active@test.com',
            'password' => $longPassword
        ];

        $response = $this->getJson('/api/users/login?' . http_build_query($loginData));

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function multiple_failed_logins_still_allow_successful_login()
    {
        // Multiple failed attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->getJson('/api/users/login?' . http_build_query([
                'email' => 'active@test.com',
                'password' => 'wrongpassword'
            ]));

            $response->assertStatus(200)
                ->assertJson(['success' => false]);
        }

        // Successful login should still work
        $response = $this->getJson('/api/users/login?' . http_build_query([
            'email' => 'active@test.com',
            'password' => 'password123'
        ]));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
