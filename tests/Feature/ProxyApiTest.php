<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Proxy;
use App\Models\Tag;
use Laravel\Sanctum\Sanctum;

class ProxyApiTest extends TestCase
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
    public function it_can_get_proxies_list()
    {
        Sanctum::actingAs($this->regularUser);

        Proxy::factory()->count(3)->create([
            'created_by' => $this->regularUser->id
        ]);

        $response = $this->getJson('/api/proxies/');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'raw_proxy',
                            'status',
                            'created_by',
                            'updated_by',
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
    public function it_can_get_single_proxy()
    {
        Sanctum::actingAs($this->regularUser);

        $proxy = Proxy::factory()->create([
            'created_by' => $this->regularUser->id,
            'updated_by' => $this->regularUser->id
        ]);

        $response = $this->getJson("/api/proxies/{$proxy->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'raw_proxy',
                    'status',
                    'created_by',
                    'updated_by'
                ]
            ]);
    }

    /** @test */
    public function it_can_create_proxy()
    {
        Sanctum::actingAs($this->regularUser);

        $proxyData = [
            'raw_proxy' => '192.168.1.1:8080',
            'status' => 'active'
        ];

        $response = $this->postJson('/api/proxies/create', $proxyData);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'raw_proxy',
                    'status',
                    'created_by',
                    'updated_by'
                ]
            ]);

        $this->assertDatabaseHas('proxies', [
            'raw_proxy' => '192.168.1.1:8080',
            'status' => 'active',
            'created_by' => $this->regularUser->id
        ]);
    }

    /** @test */
    public function it_can_create_proxy_with_default_status()
    {
        Sanctum::actingAs($this->regularUser);

        $proxyData = [
            'raw_proxy' => '192.168.1.1:8080'
        ];

        $response = $this->postJson('/api/proxies/create', $proxyData);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('proxies', [
            'raw_proxy' => '192.168.1.1:8080',
            'created_by' => $this->regularUser->id
        ]);
    }

    /** @test */
    public function it_can_bulk_create_proxies()
    {
        Sanctum::actingAs($this->regularUser);

        $proxyData = [
            'proxies' => [
                '192.168.1.1:8080',
                '192.168.1.2:8080',
                '192.168.1.3:8080'
            ]
        ];

        $response = $this->postJson('/api/proxies/bulk-create', $proxyData);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('proxies', [
            'raw_proxy' => '192.168.1.1:8080',
            'created_by' => $this->regularUser->id
        ]);

        $this->assertDatabaseHas('proxies', [
            'raw_proxy' => '192.168.1.2:8080',
            'created_by' => $this->regularUser->id
        ]);

        $this->assertDatabaseHas('proxies', [
            'raw_proxy' => '192.168.1.3:8080',
            'created_by' => $this->regularUser->id
        ]);
    }

    /** @test */
    public function it_can_update_proxy()
    {
        Sanctum::actingAs($this->regularUser);

        $proxy = Proxy::factory()->create([
            'raw_proxy' => '192.168.1.1:8080',
            'status' => 'active',
            'created_by' => $this->regularUser->id,
            'updated_by' => $this->regularUser->id
        ]);

        $updateData = [
            'raw_proxy' => '192.168.1.2:8080',
            'status' => 'inactive'
        ];

        $response = $this->postJson("/api/proxies/update/{$proxy->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('proxies', [
            'id' => $proxy->id,
            'raw_proxy' => '192.168.1.2:8080',
            'status' => 'inactive'
        ]);
    }

    /** @test */
    public function user_cannot_update_others_proxy()
    {
        Sanctum::actingAs($this->regularUser);

        $proxy = Proxy::factory()->create([
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id
        ]);

        $updateData = [
            'raw_proxy' => '192.168.1.2:8080',
            'status' => 'inactive'
        ];

        $response = $this->postJson("/api/proxies/update/{$proxy->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function admin_can_update_any_proxy()
    {
        Sanctum::actingAs($this->adminUser);

        $proxy = Proxy::factory()->create([
            'created_by' => $this->regularUser->id,
            'updated_by' => $this->regularUser->id
        ]);

        $updateData = [
            'raw_proxy' => '192.168.1.2:8080',
            'status' => 'inactive'
        ];

        $response = $this->postJson("/api/proxies/update/{$proxy->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('proxies', [
            'id' => $proxy->id,
            'raw_proxy' => '192.168.1.2:8080',
            'status' => 'inactive'
        ]);
    }

    /** @test */
    public function it_can_delete_proxy()
    {
        Sanctum::actingAs($this->regularUser);

        $proxy = Proxy::factory()->create([
            'created_by' => $this->regularUser->id,
            'updated_by' => $this->regularUser->id
        ]);

        $response = $this->getJson("/api/proxies/delete/{$proxy->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('proxies', [
            'id' => $proxy->id
        ]);
    }

    /** @test */
    public function user_cannot_delete_others_proxy()
    {
        Sanctum::actingAs($this->regularUser);

        $proxy = Proxy::factory()->create([
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id
        ]);

        $response = $this->getJson("/api/proxies/delete/{$proxy->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('proxies', [
            'id' => $proxy->id,
            'deleted_at' => null
        ]);
    }

    /** @test */
    public function it_can_toggle_proxy_status()
    {
        Sanctum::actingAs($this->regularUser);

        $proxy = Proxy::factory()->create([
            'status' => 'active',
            'created_by' => $this->regularUser->id,
            'updated_by' => $this->regularUser->id
        ]);

        $response = $this->postJson("/api/proxies/toggle-status/{$proxy->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('proxies', [
            'id' => $proxy->id,
            'status' => 'inactive'
        ]);
    }

    /** @test */
    public function it_can_add_tags_to_proxy()
    {
        Sanctum::actingAs($this->regularUser);

        $proxy = Proxy::factory()->create([
            'created_by' => $this->regularUser->id,
            'updated_by' => $this->regularUser->id
        ]);

        $tag1 = Tag::factory()->create(['name' => 'Tag1']);
        $tag2 = Tag::factory()->create(['name' => 'Tag2']);

        $response = $this->postJson("/api/proxies/add-tags/{$proxy->id}", [
            'tags' => ['Tag1', 'Tag2', 'NewTag']
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('proxy_tags', [
            'proxy_id' => $proxy->id,
            'tag_id' => $tag1->id
        ]);

        $this->assertDatabaseHas('proxy_tags', [
            'proxy_id' => $proxy->id,
            'tag_id' => $tag2->id
        ]);
    }

    /** @test */
    public function it_can_remove_tags_from_proxy()
    {
        Sanctum::actingAs($this->regularUser);

        $proxy = Proxy::factory()->create([
            'created_by' => $this->regularUser->id,
            'updated_by' => $this->regularUser->id
        ]);

        $tag1 = Tag::factory()->create(['name' => 'Tag1']);
        $tag2 = Tag::factory()->create(['name' => 'Tag2']);

        // Add tags first
        $proxy->tags()->attach([$tag1->id, $tag2->id]);

        $response = $this->postJson("/api/proxies/remove-tags/{$proxy->id}", [
            'tags' => [$tag1->id]
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('proxy_tags', [
            'proxy_id' => $proxy->id,
            'tag_id' => $tag1->id
        ]);

        $this->assertDatabaseHas('proxy_tags', [
            'proxy_id' => $proxy->id,
            'tag_id' => $tag2->id
        ]);
    }

    /** @test */
    public function it_can_test_proxy_connection()
    {
        Sanctum::actingAs($this->regularUser);

        $proxy = Proxy::factory()->create([
            'raw_proxy' => '192.168.1.1:8080',
            'created_by' => $this->regularUser->id,
            'updated_by' => $this->regularUser->id
        ]);

        $response = $this->postJson("/api/proxies/test-connection/{$proxy->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_share_proxy()
    {
        Sanctum::actingAs($this->adminUser);

        $proxy = Proxy::factory()->create([
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id
        ]);

        $response = $this->postJson("/api/proxies/share/{$proxy->id}", [
            'user_id' => $this->regularUser->id,
            'role' => 'viewer'
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('proxy_shares', [
            'proxy_id' => $proxy->id,
            'user_id' => $this->regularUser->id,
            'role' => 'viewer'
        ]);
    }

    /** @test */
    public function it_can_bulk_share_proxies()
    {
        Sanctum::actingAs($this->adminUser);

        $proxy1 = Proxy::factory()->create([
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id
        ]);

        $proxy2 = Proxy::factory()->create([
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id
        ]);

        $response = $this->postJson('/api/proxies/bulk-share', [
            'proxy_ids' => [$proxy1->id, $proxy2->id],
            'user_id' => $this->regularUser->id,
            'role' => 'viewer'
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('proxy_shares', [
            'proxy_id' => $proxy1->id,
            'user_id' => $this->regularUser->id,
            'role' => 'viewer'
        ]);

        $this->assertDatabaseHas('proxy_shares', [
            'proxy_id' => $proxy2->id,
            'user_id' => $this->regularUser->id,
            'role' => 'viewer'
        ]);
    }

    /** @test */
    public function it_can_get_proxy_shares()
    {
        Sanctum::actingAs($this->adminUser);

        $proxy = Proxy::factory()->create([
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id
        ]);

        $response = $this->getJson("/api/proxies/shares/{$proxy->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_filter_proxies_by_search()
    {
        Sanctum::actingAs($this->regularUser);

        Proxy::factory()->create([
            'raw_proxy' => '192.168.1.1:8080',
            'created_by' => $this->regularUser->id
        ]);

        Proxy::factory()->create([
            'raw_proxy' => '10.0.0.1:3128',
            'created_by' => $this->regularUser->id
        ]);

        $response = $this->getJson('/api/proxies/?search=192.168');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_filter_proxies_by_status()
    {
        Sanctum::actingAs($this->regularUser);

        Proxy::factory()->create([
            'status' => 'active',
            'created_by' => $this->regularUser->id
        ]);

        Proxy::factory()->create([
            'status' => 'inactive',
            'created_by' => $this->regularUser->id
        ]);

        $response = $this->getJson('/api/proxies/?status=active');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_filter_proxies_by_tags()
    {
        Sanctum::actingAs($this->regularUser);

        $tag = Tag::factory()->create(['name' => 'Production']);

        $proxy1 = Proxy::factory()->create([
            'created_by' => $this->regularUser->id
        ]);

        $proxy2 = Proxy::factory()->create([
            'created_by' => $this->regularUser->id
        ]);

        $proxy1->tags()->attach($tag->id);

        $response = $this->getJson("/api/proxies/?tags={$tag->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_paginate_proxies()
    {
        Sanctum::actingAs($this->regularUser);

        Proxy::factory()->count(50)->create([
            'created_by' => $this->regularUser->id
        ]);

        $response = $this->getJson('/api/proxies/?per_page=10&page=2');

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
    public function user_can_only_see_own_and_shared_proxies()
    {
        Sanctum::actingAs($this->regularUser);

        // Own proxy
        $ownProxy = Proxy::factory()->create([
            'created_by' => $this->regularUser->id
        ]);

        // Other user's proxy (should not be visible)
        $otherProxy = Proxy::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        $response = $this->getJson('/api/proxies/');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Should only include own proxy in results
        $data = $response->json('data.data');
        $proxyIds = collect($data)->pluck('id')->toArray();

        $this->assertContains($ownProxy->id, $proxyIds);
        $this->assertNotContains($otherProxy->id, $proxyIds);
    }

    /** @test */
    public function admin_can_see_all_proxies()
    {
        Sanctum::actingAs($this->adminUser);

        // Admin's proxy
        $adminProxy = Proxy::factory()->create([
            'created_by' => $this->adminUser->id
        ]);

        // Regular user's proxy
        $userProxy = Proxy::factory()->create([
            'created_by' => $this->regularUser->id
        ]);

        $response = $this->getJson('/api/proxies/');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data.data');
        $proxyIds = collect($data)->pluck('id')->toArray();

        $this->assertContains($adminProxy->id, $proxyIds);
        $this->assertContains($userProxy->id, $proxyIds);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_proxies()
    {
        $response = $this->getJson('/api/proxies/');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_proxy_creation_data()
    {
        Sanctum::actingAs($this->regularUser);

        // Test missing raw_proxy
        $response = $this->postJson('/api/proxies/create', []);

        $response->assertStatus(422);

        // Test invalid proxy format
        $response = $this->postJson('/api/proxies/create', [
            'raw_proxy' => 'invalid-format'
        ]);

        $response->assertStatus(422);

        // Test invalid status
        $response = $this->postJson('/api/proxies/create', [
            'raw_proxy' => '192.168.1.1:8080',
            'status' => 'invalid-status'
        ]);

        $response->assertStatus(422);
    }
}
