<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\Engine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;
    protected Engine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('tenancy:install');

        $this->tenant = Tenant::create(['id' => 'test', 'name' => 'Test Tenant']);
        $this->tenant->domains()->create(['domain' => 'test.localhost']);

        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test')->plainTextToken;

        $this->engine = Engine::factory()->create();
    }

    public function test_can_create_agent(): void
    {
        $data = [
            'name' => 'Test Agent',
            'description' => 'A test agent',
            'type' => 'custom',
            'engines_used' => [$this->engine->id],
            'config' => [
                'system_prompt' => 'You are a helpful assistant',
                'temperature' => 0.7,
            ],
            'status' => 'draft',
        ];

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/agents', $data);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Test Agent']);
    }

    public function test_can_execute_agent(): void
    {
        $agent = Agent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'engines_used' => [$this->engine->id],
            'status' => 'active',
        ]);

        $data = [
            'input' => ['message' => 'Hello, agent!'],
            'context' => ['session_id' => '123'],
        ];

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$agent->id}/execute", $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'status', 'input_data']
            ]);
    }

    public function test_can_clone_agent(): void
    {
        $agent = Agent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Agent',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$agent->id}/clone");

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Original Agent (Copy)']);
    }

    public function test_can_publish_agent(): void
    {
        $agent = Agent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'is_published' => false,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$agent->id}/publish");

        $response->assertStatus(200);

        $this->assertDatabaseHas('agents', [
            'id' => $agent->id,
            'is_published' => true,
        ]);
    }
}
