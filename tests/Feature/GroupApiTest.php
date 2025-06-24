<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use Laravel\Sanctum\Sanctum;

class GroupApiTest extends TestCase
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
            'is_active' => 1
        ]);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'is_admin' => 0,
            'is_active' => 1
        ]);
    }

    /** @test */
    public function it_can_get_groups_list()
    {
        Sanctum::actingAs($this->regularUser);

        Group::factory()->count(3)->create();

        $response = $this->getJson('/api/groups/');

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
                                 'order',
                                 'creator_id',
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
    public function it_can_get_groups_count()
    {
        Sanctum::actingAs($this->regularUser);

        Group::factory()->count(5)->create();

        $response = $this->getJson('/api/groups/count');

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data'
                 ]);
    }

    /** @test */
    public function admin_can_create_group()
    {
        Sanctum::actingAs($this->adminUser);

        $groupData = [
            'name' => 'Test Group',
            'order' => 1
        ];

        $response = $this->postJson('/api/groups/create', $groupData);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'id',
                         'name',
                         'order',
                         'creator_id'
                     ]
                 ]);

        $this->assertDatabaseHas('groups', [
            'name' => 'Test Group',
            'order' => 1,
            'creator_id' => $this->adminUser->id
        ]);
    }

    /** @test */
    public function regular_user_cannot_create_group()
    {
        Sanctum::actingAs($this->regularUser);

        $groupData = [
            'name' => 'Test Group',
            'order' => 1
        ];

        $response = $this->postJson('/api/groups/create', $groupData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => false,
                     'message' => 'admin_required'
                 ]);
    }

    /** @test */
    public function admin_can_update_group()
    {
        Sanctum::actingAs($this->adminUser);

        $group = Group::factory()->create([
            'name' => 'Original Name',
            'creator_id' => $this->adminUser->id
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'order' => 2
        ];

        $response = $this->postJson("/api/groups/update/{$group->id}", $updateData);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'name' => 'Updated Name',
            'order' => 2
        ]);
    }

    /** @test */
    public function regular_user_cannot_update_group()
    {
        Sanctum::actingAs($this->regularUser);

        $group = Group::factory()->create();

        $updateData = [
            'name' => 'Updated Name',
            'order' => 2
        ];

        $response = $this->postJson("/api/groups/update/{$group->id}", $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => false,
                     'message' => 'admin_required'
                 ]);
    }

    /** @test */
    public function admin_can_delete_group()
    {
        Sanctum::actingAs($this->adminUser);

        $group = Group::factory()->create([
            'creator_id' => $this->adminUser->id
        ]);

        $response = $this->getJson("/api/groups/delete/{$group->id}");

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertSoftDeleted('groups', [
            'id' => $group->id
        ]);
    }

    /** @test */
    public function regular_user_cannot_delete_group()
    {
        Sanctum::actingAs($this->regularUser);

        $group = Group::factory()->create();

        $response = $this->getJson("/api/groups/delete/{$group->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => false,
                     'message' => 'admin_required'
                 ]);
    }

    /** @test */
    public function it_can_share_group()
    {
        Sanctum::actingAs($this->adminUser);

        $group = Group::factory()->create([
            'creator_id' => $this->adminUser->id
        ]);

        $response = $this->getJson("/api/groups/share/{$group->id}");

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_get_group_shares()
    {
        Sanctum::actingAs($this->adminUser);

        $group = Group::factory()->create([
            'creator_id' => $this->adminUser->id
        ]);

        $response = $this->getJson("/api/groups/shares/{$group->id}");

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_groups()
    {
        $response = $this->getJson('/api/groups/');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_can_search_groups()
    {
        Sanctum::actingAs($this->regularUser);

        Group::factory()->create(['name' => 'Test Group 1']);
        Group::factory()->create(['name' => 'Test Group 2']);
        Group::factory()->create(['name' => 'Different Name']);

        $response = $this->getJson('/api/groups/?search=Test');

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_paginate_groups()
    {
        Sanctum::actingAs($this->regularUser);

        Group::factory()->count(50)->create();

        $response = $this->getJson('/api/groups/?per_page=10&page=2');

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
}