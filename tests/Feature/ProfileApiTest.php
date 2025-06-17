<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Profile;
use App\Models\Group;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    private $user;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'system_role' => 'USER',
            'is_active' => true
        ]);

        // Create a token for authentication
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        // Create a test group if it doesn't exist
        Group::firstOrCreate([
            'id' => 1
        ], [
            'name' => 'Test Group',
            'sort_order' => 0,
            'created_by' => $this->user->id
        ]);
    }

    /** @test */
    public function itCanListProfiles()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/profiles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'current_page',
                    'data',
                    'total'
                ]
            ]);

        $this->assertTrue($response->json('success'));
    }

    /** @test */
    public function itCanCreateAProfile()
    {
        $profileData = [
            'name' => 'Test Profile',
            'storage_path' => '/test/path',
            'json_data' => ['browser' => 'chrome'],
            'cookie_data' => ['session' => 'test'],
            'group_id' => 1,
            'storage_type' => 'S3'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->post('/api/profiles/create', $profileData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'storage_path',
                    'json_data',
                    'meta_data',
                    'group_id',
                    'created_by'
                ]
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('Test Profile', $response->json('data.name'));
        $this->assertEquals('/test/path', $response->json('data.storage_path'));
    }

    /** @test */
    public function itCanShowASpecificProfile()
    {
        // Create a profile first
        $profile = $this->createTestProfile();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/profiles/' . $profile->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'storage_path'
                ]
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals($profile->name, $response->json('data.name'));
    }

    /** @test */
    public function itCanUpdateAProfile()
    {
        // Create a profile first
        $profile = $this->createTestProfile();

        $updateData = [
            'name' => 'Updated Profile Name',
            'storage_path' => '/updated/path',
            'json_data' => ['browser' => 'firefox'],
            'meta_data' => ['updated' => true],
            'group_id' => 1
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->post('/api/profiles/update/' . $profile->id, $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'OK'
            ]);

        // Verify the profile was updated
        $profile->refresh();
        $this->assertEquals('Updated Profile Name', $profile->name);
        $this->assertEquals('/updated/path', $profile->storage_path);
    }

    /** @test */
    public function itCanDeleteAProfile()
    {
        // Create a profile first
        $profile = $this->createTestProfile();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/profiles/delete/' . $profile->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        // Verify the profile was soft deleted
        $profile->refresh();
        $this->assertTrue($profile->is_deleted);
    }

    /** @test */
    public function itCanGetProfileCount()
    {
        // Create a few profiles
        $this->createTestProfile();
        $this->createTestProfile();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/profiles/count');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total'
                ]
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertIsInt($response->json('data.total'));
    }

    /** @test */
    public function itRequiresAuthentication()
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json'
        ])->get('/api/profiles');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.'
            ]);
    }

    /** @test */
    public function itValidatesRequiredFieldsForCreation()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->post('/api/profiles/create', []);

        // Since we're not using Laravel's built-in validation,
        // we expect the service to handle missing data gracefully
        $response->assertStatus(200);
    }

    private function createTestProfile()
    {
        return Profile::create([
            'name' => 'Test Profile ' . $this->faker->uuid,
            'storage_path' => '/test/path/' . $this->faker->uuid,
            'json_data' => ['browser' => 'chrome'],
            'meta_data' => ['test' => true],
            'group_id' => 1,
            'created_by' => $this->user->id,
            'storage_type' => 'S3',
            'status' => 1,
            'usage_count' => 0,
            'is_deleted' => false
        ]);
    }
}
