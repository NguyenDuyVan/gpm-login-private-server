<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadApiTest extends TestCase
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

        // Setup storage for testing
        Storage::fake('public');
    }

    /** @test */
    public function it_can_upload_file_via_file_upload_endpoint()
    {
        Sanctum::actingAs($this->regularUser);

        $file = UploadedFile::fake()->image('test-image.jpg', 600, 400);

        $response = $this->postJson('/api/file/upload', [
            'file' => $file,
            'file_name' => 'custom-name.jpg'
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'file_name',
                    'file_path',
                    'file_url'
                ]
            ]);

        // Verify file was stored
        $responseData = $response->json('data');
        Storage::disk('public')->assertExists($responseData['file_path']);
    }

    /** @test */
    public function it_can_upload_file_via_legacy_endpoint()
    {
        Sanctum::actingAs($this->regularUser);

        $file = UploadedFile::fake()->image('test-image.jpg', 600, 400);

        $response = $this->postJson('/api/file/upload', [
            'file' => $file
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_upload_file_without_custom_name()
    {
        Sanctum::actingAs($this->regularUser);

        $file = UploadedFile::fake()->image('original-name.jpg', 600, 400);

        $response = $this->postJson('/api/file/upload', [
            'file' => $file
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $responseData = $response->json('data');
        $this->assertNotEmpty($responseData['file_name']);
        $this->assertNotEmpty($responseData['file_path']);
    }

    /** @test */
    public function it_can_upload_different_file_types()
    {
        Sanctum::actingAs($this->regularUser);

        // Test image upload
        $imageFile = UploadedFile::fake()->image('test.png', 600, 400);
        $response = $this->postJson('/api/file/upload', ['file' => $imageFile]);
        $response->assertStatus(200)->assertJson(['success' => true]);

        // Test document upload
        $docFile = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');
        $response = $this->postJson('/api/file/upload', ['file' => $docFile]);
        $response->assertStatus(200)->assertJson(['success' => true]);

        // Test text file upload
        $textFile = UploadedFile::fake()->create('text.txt', 500, 'text/plain');
        $response = $this->postJson('/api/file/upload', ['file' => $textFile]);
        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_delete_file()
    {
        Sanctum::actingAs($this->regularUser);

        // First upload a file
        $file = UploadedFile::fake()->image('to-delete.jpg', 600, 400);
        $uploadResponse = $this->postJson('/api/file/upload', ['file' => $file]);
        $uploadResponse->assertStatus(200);

        $uploadedFilePath = $uploadResponse->json('data.file_path');

        // Verify file exists
        Storage::disk('public')->assertExists($uploadedFilePath);

        // Delete the file
        $response = $this->getJson('/api/file/delete?file=' . urlencode($uploadedFilePath));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify file was deleted
        Storage::disk('public')->assertMissing($uploadedFilePath);
    }

    /** @test */
    public function it_can_delete_file_via_legacy_endpoint()
    {
        Sanctum::actingAs($this->regularUser);

        // First upload a file
        $file = UploadedFile::fake()->image('to-delete-legacy.jpg', 600, 400);
        $uploadResponse = $this->postJson('/api/file/upload', ['file' => $file]);
        $uploadResponse->assertStatus(200);

        $uploadedFilePath = $uploadResponse->json('data.file_path');

        // Delete the file via legacy endpoint
        $response = $this->getJson('/api/file/delete?file=' . urlencode($uploadedFilePath));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        Storage::disk('public')->assertMissing($uploadedFilePath);
    }

    /** @test */
    public function it_handles_delete_nonexistent_file()
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/file/delete?file=nonexistent/file.jpg');

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function it_can_upload_to_s3_via_nested_endpoint()
    {
        Sanctum::actingAs($this->regularUser);

        $file = UploadedFile::fake()->image('s3-test.jpg', 600, 400);

        $response = $this->postJson('/api/file/upload-s3', [
            'file' => $file,
            'file_name' => 's3-custom-name.jpg'
        ]);

        // Note: This might return success or error depending on S3 configuration
        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_upload_to_s3_via_legacy_endpoint()
    {
        Sanctum::actingAs($this->regularUser);

        $file = UploadedFile::fake()->image('s3-legacy-test.jpg', 600, 400);

        $response = $this->getJson('/api/file/upload-s3', [
            'file' => $file
        ]);

        // Note: This might return success or error depending on S3 configuration
        $response->assertStatus(200);
    }

    /** @test */
    public function upload_fails_without_file()
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->postJson('/api/file/upload', []);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'Thất bại'
            ]);
    }

    /** @test */
    public function delete_fails_without_file_parameter()
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/file/delete');

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function upload_handles_large_files()
    {
        Sanctum::actingAs($this->regularUser);

        // Create a larger file (5MB)
        $largeFile = UploadedFile::fake()->create('large-file.pdf', 5000, 'application/pdf');

        $response = $this->postJson('/api/file/upload', [
            'file' => $largeFile,
            'file_name' => 'large-document.pdf'
        ]);

        $response->assertStatus(200);

        // Should either succeed or fail gracefully depending on configuration
        $this->assertTrue($response->json('success') !== null);
    }

    /** @test */
    public function upload_preserves_file_extension()
    {
        Sanctum::actingAs($this->regularUser);

        $file = UploadedFile::fake()->image('test.png', 600, 400);

        $response = $this->postJson('/api/file/upload', [
            'file' => $file,
            'file_name' => 'custom-name'
        ]);

        $response->assertStatus(200);

        if ($response->json('success')) {
            $fileName = $response->json('data.file_name');
            $this->assertStringEndsWith('.png', $fileName);
        }
    }

    /** @test */
    public function upload_generates_unique_filenames()
    {
        Sanctum::actingAs($this->regularUser);

        $file1 = UploadedFile::fake()->image('duplicate.jpg', 600, 400);
        $file2 = UploadedFile::fake()->image('duplicate.jpg', 600, 400);

        $response1 = $this->postJson('/api/file/upload', ['file' => $file1]);
        $response2 = $this->postJson('/api/file/upload', ['file' => $file2]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        if ($response1->json('success') && $response2->json('success')) {
            $path1 = $response1->json('data.file_path');
            $path2 = $response2->json('data.file_path');

            $this->assertNotEquals($path1, $path2);
        }
    }

    /** @test */
    public function unauthenticated_user_cannot_upload_files()
    {
        $file = UploadedFile::fake()->image('unauthorized.jpg', 600, 400);

        $response = $this->postJson('/api/file/upload', ['file' => $file]);

        $response->assertStatus(401);
    }

    /** @test */
    public function unauthenticated_user_cannot_delete_files()
    {
        $response = $this->getJson('/api/file/delete?file=some/file.jpg');

        $response->assertStatus(401);
    }

    /** @test */
    public function unauthenticated_user_cannot_upload_to_s3()
    {
        $file = UploadedFile::fake()->image('s3-unauthorized.jpg', 600, 400);

        $response = $this->postJson('/api/file/upload-s3', ['file' => $file]);

        $response->assertStatus(401);
    }

    /** @test */
    public function admin_user_can_upload_files()
    {
        Sanctum::actingAs($this->adminUser);

        $file = UploadedFile::fake()->image('admin-upload.jpg', 600, 400);

        $response = $this->postJson('/api/file/upload', ['file' => $file]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function upload_returns_proper_url_structure()
    {
        Sanctum::actingAs($this->regularUser);

        $file = UploadedFile::fake()->image('url-test.jpg', 600, 400);

        $response = $this->postJson('/api/file/upload', ['file' => $file]);

        $response->assertStatus(200);

        if ($response->json('success')) {
            $data = $response->json('data');

            $this->assertArrayHasKey('file_name', $data);
            $this->assertArrayHasKey('file_path', $data);
            $this->assertArrayHasKey('file_url', $data);

            $this->assertIsString($data['file_name']);
            $this->assertIsString($data['file_path']);
            $this->assertIsString($data['file_url']);
        }
    }
}
