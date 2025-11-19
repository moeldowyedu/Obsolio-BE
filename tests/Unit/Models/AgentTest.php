<?php

namespace Tests\Unit\Models;

use App\Models\Agent;
use App\Models\AgentExecution;
use App\Models\JobFlow;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('tenancy:install');

        $this->tenant = Tenant::create(['id' => 'test', 'name' => 'Test Tenant']);
        $this->tenant->domains()->create(['domain' => 'test.localhost']);

        tenancy()->initialize($this->tenant);
    }

    public function test_agent_engines_used_is_cast_to_array(): void
    {
        $agent = Agent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'engines_used' => ['engine1', 'engine2'],
        ]);

        $this->assertIsArray($agent->engines_used);
        $this->assertCount(2, $agent->engines_used);
    }

    public function test_agent_config_is_cast_to_array(): void
    {
        $config = [
            'system_prompt' => 'Test prompt',
            'temperature' => 0.7,
            'max_tokens' => 1000,
        ];

        $agent = Agent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'config' => $config,
        ]);

        $this->assertIsArray($agent->config);
        $this->assertEquals('Test prompt', $agent->config['system_prompt']);
        $this->assertEquals(0.7, $agent->config['temperature']);
    }

    public function test_agent_has_created_by_relationship(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $agent = Agent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $agent->createdBy);
        $this->assertEquals($user->id, $agent->createdBy->id);
    }

    public function test_agent_has_job_flows_relationship(): void
    {
        $agent = Agent::factory()->create(['tenant_id' => $this->tenant->id]);
        JobFlow::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => $agent->id,
        ]);

        $this->assertCount(2, $agent->jobFlows);
    }

    public function test_agent_has_executions_relationship(): void
    {
        $agent = Agent::factory()->create(['tenant_id' => $this->tenant->id]);
        AgentExecution::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => $agent->id,
        ]);

        $this->assertCount(3, $agent->executions);
    }

    public function test_agent_is_published_is_boolean(): void
    {
        $agent = Agent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_published' => true,
        ]);

        $this->assertTrue($agent->is_published);
        $this->assertIsBool($agent->is_published);
    }
}
