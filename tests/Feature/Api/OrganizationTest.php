<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('tenancy:install');

        $this->tenant = Tenant::create(['id' => 'test', 'name' => 'Test Tenant']);
        $this->tenant->domains()->create(['domain' => 'test.localhost']);

        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_can_list_organizations(): void
    {
        Organization::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/organizations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'tenant_id']
                ]
            ]);
    }

    public function test_can_create_organization(): void
    {
        $data = [
            'name' => 'New Organization',
            'industry' => 'Technology',
            'country' => 'USA',
        ];

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/organizations', $data);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Organization']);

        $this->assertDatabaseHas('organizations', [
            'name' => 'New Organization',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_can_show_organization(): void
    {
        $organization = Organization::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/organizations/{$organization->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $organization->id]);
    }

    public function test_can_update_organization(): void
    {
        $organization = Organization::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v1/organizations/{$organization->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_delete_organization(): void
    {
        $organization = Organization::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v1/organizations/{$organization->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
    }

    public function test_validation_fails_for_invalid_data(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/organizations', [
                'name' => '', // Invalid: name is required
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
