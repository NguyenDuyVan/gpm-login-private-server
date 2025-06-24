<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tag;
use Laravel\Sanctum\Sanctum;

class TagApiTest extends TestCase
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
    public function it_can_get_tags_list()
    {
        Sanctum::actingAs($this->regularUser);

        Tag::factory()->count(3)->create();

        $response = $this->getJson('/api/tags/');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'color',
                            'created_by',
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
    public function it_can_get_tags_with_count()
    {
        Sanctum::actingAs($this->regularUser);

        Tag::factory()->count(3)->create();

        $response = $this->getJson('/api/tags/with-count');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_get_single_tag()
    {
        Sanctum::actingAs($this->regularUser);

        $tag = Tag::factory()->create([
            'name' => 'Test Tag',
            'color' => '#ff0000'
        ]);

        $response = $this->getJson("/api/tags/{$tag->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'color',
                    'created_by'
                ]
            ]);
    }

    /** @test */
    public function it_can_create_tag()
    {
        Sanctum::actingAs($this->regularUser);

        $tagData = [
            'name' => 'New Tag',
            'color' => '#00ff00'
        ];

        $response = $this->postJson('/api/tags/create', $tagData);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'color',
                    'created_by'
                ]
            ]);

        $this->assertDatabaseHas('tags', [
            'name' => 'New Tag',
            'color' => '#00ff00',
            'created_by' => $this->regularUser->id
        ]);
    }

    /** @test */
    public function it_can_create_tag_with_default_color()
    {
        Sanctum::actingAs($this->regularUser);

        $tagData = [
            'name' => 'Default Color Tag'
        ];

        $response = $this->postJson('/api/tags/create', $tagData);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tags', [
            'name' => 'Default Color Tag',
            'created_by' => $this->regularUser->id
        ]);
    }

    /** @test */
    public function it_can_update_tag()
    {
        Sanctum::actingAs($this->regularUser);

        $tag = Tag::factory()->create([
            'name' => 'Original Tag',
            'color' => '#ff0000',
            'created_by' => $this->regularUser->id
        ]);

        $updateData = [
            'name' => 'Updated Tag',
            'color' => '#00ff00'
        ];

        $response = $this->postJson("/api/tags/update/{$tag->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'Updated Tag',
            'color' => '#00ff00'
        ]);
    }

    /** @test */
    public function user_cannot_update_others_tag()
    {
        Sanctum::actingAs($this->regularUser);

        $tag = Tag::factory()->create([
            'name' => 'Other User Tag',
            'created_by' => $this->adminUser->id
        ]);

        $updateData = [
            'name' => 'Updated Tag',
            'color' => '#00ff00'
        ];

        $response = $this->postJson("/api/tags/update/{$tag->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function admin_can_update_any_tag()
    {
        Sanctum::actingAs($this->adminUser);

        $tag = Tag::factory()->create([
            'name' => 'Any Tag',
            'created_by' => $this->regularUser->id
        ]);

        $updateData = [
            'name' => 'Admin Updated Tag',
            'color' => '#0000ff'
        ];

        $response = $this->postJson("/api/tags/update/{$tag->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'Admin Updated Tag',
            'color' => '#0000ff'
        ]);
    }

    /** @test */
    public function it_can_delete_tag()
    {
        Sanctum::actingAs($this->regularUser);

        $tag = Tag::factory()->create([
            'created_by' => $this->regularUser->id
        ]);

        $response = $this->getJson("/api/tags/delete/{$tag->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('tags', [
            'id' => $tag->id
        ]);
    }

    /** @test */
    public function user_cannot_delete_others_tag()
    {
        Sanctum::actingAs($this->regularUser);

        $tag = Tag::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        $response = $this->getJson("/api/tags/delete/{$tag->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'deleted_at' => null
        ]);
    }

    /** @test */
    public function admin_can_delete_any_tag()
    {
        Sanctum::actingAs($this->adminUser);

        $tag = Tag::factory()->create([
            'created_by' => $this->regularUser->id
        ]);

        $response = $this->getJson("/api/tags/delete/{$tag->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('tags', [
            'id' => $tag->id
        ]);
    }

    /** @test */
    public function it_can_search_tags()
    {
        Sanctum::actingAs($this->regularUser);

        Tag::factory()->create(['name' => 'Frontend Tag']);
        Tag::factory()->create(['name' => 'Backend Tag']);
        Tag::factory()->create(['name' => 'Mobile Tag']);

        $response = $this->getJson('/api/tags/?search=Frontend');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_paginate_tags()
    {
        Sanctum::actingAs($this->regularUser);

        Tag::factory()->count(50)->create();

        $response = $this->getJson('/api/tags/?per_page=10&page=2');

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
    public function unauthenticated_user_cannot_access_tags()
    {
        $response = $this->getJson('/api/tags/');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_tag_creation_data()
    {
        Sanctum::actingAs($this->regularUser);

        // Test missing name
        $response = $this->postJson('/api/tags/create', []);

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function it_prevents_duplicate_tag_names()
    {
        Sanctum::actingAs($this->regularUser);

        Tag::factory()->create(['name' => 'Duplicate Tag']);

        $tagData = [
            'name' => 'Duplicate Tag',
            'color' => '#ff0000'
        ];

        $response = $this->postJson('/api/tags/create', $tagData);

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }
}
