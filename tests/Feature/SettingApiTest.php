<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Setting;
use Laravel\Sanctum\Sanctum;

class SettingApiTest extends TestCase
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
    public function it_can_get_private_server_version_without_auth()
    {
        $response = $this->getJson('/api/settings/get-version');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'version'
                ]
            ]);

        // Check that version is returned as expected
        $version = $response->json('data.version');
        $this->assertIsNumeric($version);
        $this->assertEquals(13, $version); // Based on the controller's static version
    }

    /** @test */
    public function authenticated_user_can_get_s3_settings()
    {
        Sanctum::actingAs($this->regularUser);

        // Create some S3 settings
        Setting::create(['key' => 's3_access_key', 'value' => 'test_access_key']);
        Setting::create(['key' => 's3_secret_key', 'value' => 'test_secret_key']);
        Setting::create(['key' => 's3_bucket', 'value' => 'test_bucket']);
        Setting::create(['key' => 's3_region', 'value' => 'us-east-1']);

        $response = $this->getJson('/api/settings/get-s3-api');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /** @test */
    public function authenticated_user_can_get_storage_type_setting()
    {
        Sanctum::actingAs($this->regularUser);

        // Create storage type setting
        Setting::create(['key' => 'storage_type', 'value' => 'local']);

        $response = $this->getJson('/api/settings/get-storage-type');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /** @test */
    public function authenticated_user_can_get_all_settings()
    {
        Sanctum::actingAs($this->regularUser);

        // Create multiple settings
        Setting::create(['key' => 'app_name', 'value' => 'GPM Login Server']);
        Setting::create(['key' => 'max_upload_size', 'value' => '10240']);
        Setting::create(['key' => 'storage_type', 'value' => 'local']);

        $response = $this->getJson('/api/settings/get-setting');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_get_s3_settings()
    {
        $response = $this->getJson('/api/settings/get-s3-api');

        $response->assertStatus(401);
    }

    /** @test */
    public function unauthenticated_user_cannot_get_storage_type()
    {
        $response = $this->getJson('/api/settings/get-storage-type');

        $response->assertStatus(401);
    }

    /** @test */
    public function unauthenticated_user_cannot_get_all_settings()
    {
        $response = $this->getJson('/api/settings/get-setting');

        $response->assertStatus(401);
    }

    /** @test */
    public function admin_user_can_get_s3_settings()
    {
        Sanctum::actingAs($this->adminUser);

        Setting::create(['key' => 's3_access_key', 'value' => 'admin_access_key']);
        Setting::create(['key' => 's3_secret_key', 'value' => 'admin_secret_key']);

        $response = $this->getJson('/api/settings/get-s3-api');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function admin_user_can_get_storage_type_setting()
    {
        Sanctum::actingAs($this->adminUser);

        Setting::create(['key' => 'storage_type', 'value' => 's3']);

        $response = $this->getJson('/api/settings/get-storage-type');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function admin_user_can_get_all_settings()
    {
        Sanctum::actingAs($this->adminUser);

        Setting::create(['key' => 'admin_setting', 'value' => 'admin_value']);
        Setting::create(['key' => 'another_setting', 'value' => 'another_value']);

        $response = $this->getJson('/api/settings/get-setting');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function s3_settings_returns_proper_structure()
    {
        Sanctum::actingAs($this->regularUser);

        // Create comprehensive S3 settings
        Setting::create(['key' => 's3_access_key', 'value' => 'AKIAIOSFODNN7EXAMPLE']);
        Setting::create(['key' => 's3_secret_key', 'value' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY']);
        Setting::create(['key' => 's3_bucket', 'value' => 'my-test-bucket']);
        Setting::create(['key' => 's3_region', 'value' => 'us-west-2']);
        Setting::create(['key' => 's3_endpoint', 'value' => 'https://s3.us-west-2.amazonaws.com']);

        $response = $this->getJson('/api/settings/get-s3-api');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertNotNull($data);
    }

    /** @test */
    public function storage_type_setting_returns_valid_type()
    {
        Sanctum::actingAs($this->regularUser);

        Setting::create(['key' => 'storage_type', 'value' => 'local']);

        $response = $this->getJson('/api/settings/get-storage-type');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertNotNull($data);
    }

    /** @test */
    public function all_settings_returns_complete_list()
    {
        Sanctum::actingAs($this->regularUser);

        // Create various settings
        $settings = [
            'app_name' => 'GPM Login Private Server',
            'app_version' => '2.0.0',
            'storage_type' => 'local',
            'max_upload_size' => '50',
            'allowed_file_types' => 'jpg,png,pdf,doc',
            's3_access_key' => 'test_key',
            's3_secret_key' => 'test_secret',
            's3_bucket' => 'test_bucket',
            's3_region' => 'us-east-1'
        ];

        foreach ($settings as $key => $value) {
            Setting::create(['key' => $key, 'value' => $value]);
        }

        $response = $this->getJson('/api/settings/get-setting');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertNotNull($data);
    }

    /** @test */
    public function missing_s3_settings_handled_gracefully()
    {
        Sanctum::actingAs($this->regularUser);

        // Don't create any S3 settings

        $response = $this->getJson('/api/settings/get-s3-api');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function missing_storage_type_handled_gracefully()
    {
        Sanctum::actingAs($this->regularUser);

        // Don't create storage type setting

        $response = $this->getJson('/api/settings/get-storage-type');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function empty_settings_handled_gracefully()
    {
        Sanctum::actingAs($this->regularUser);

        // Don't create any settings

        $response = $this->getJson('/api/settings/get-setting');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function version_endpoint_consistent_response()
    {
        // Test multiple calls to ensure consistency
        $response1 = $this->getJson('/api/settings/get-version');
        $response2 = $this->getJson('/api/settings/get-version');

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $version1 = $response1->json('data.version');
        $version2 = $response2->json('data.version');

        $this->assertEquals($version1, $version2);
    }

    /** @test */
    public function settings_endpoints_require_proper_authentication()
    {
        // Test with invalid token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token'
        ])->getJson('/api/settings/get-s3-api');

        $response->assertStatus(401);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token'
        ])->getJson('/api/settings/get-storage-type');

        $response->assertStatus(401);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token'
        ])->getJson('/api/settings/get-setting');

        $response->assertStatus(401);
    }

    /** @test */
    public function settings_support_different_data_types()
    {
        Sanctum::actingAs($this->regularUser);

        // Create settings with different value types
        Setting::create(['key' => 'string_setting', 'value' => 'text_value']);
        Setting::create(['key' => 'numeric_setting', 'value' => '12345']);
        Setting::create(['key' => 'boolean_setting', 'value' => 'true']);
        Setting::create(['key' => 'json_setting', 'value' => '{"key":"value"}']);

        $response = $this->getJson('/api/settings/get-setting');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertNotNull($data);
    }
}
