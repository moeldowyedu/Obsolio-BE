<?php

namespace Tests\Feature;

use App\Listeners\CreateTrialSubscription;
use App\Models\Agent;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantAgent;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that free plan assigns default agents after email verification
     */
    public function test_free_plan_assigns_default_agents()
    {
        // Create some free agents
        $agent1 = Agent::factory()->create([
            'name' => 'Email Assistant',
            'slug' => 'email-assistant',
            'price_model' => 'free',
            'is_active' => true,
            'is_featured' => true,
        ]);

        $agent2 = Agent::factory()->create([
            'name' => 'Task Manager',
            'slug' => 'task-manager',
            'price_model' => 'free',
            'is_active' => true,
            'is_featured' => true,
        ]);

        $agent3 = Agent::factory()->create([
            'name' => 'Calendar Bot',
            'slug' => 'calendar-bot',
            'price_model' => 'free',
            'is_active' => true,
            'is_featured' => true,
        ]);

        // Create a free plan that allows 2 agents
        $freePlan = SubscriptionPlan::factory()->create([
            'name' => 'Free Plan',
            'type' => 'organization',
            'tier' => 'free',
            'price_monthly' => 0.00,
            'max_agents' => 2,
            'trial_days' => 14,
            'is_active' => true,
            'is_published' => true,
            'is_default' => true,
        ]);

        // Create tenant and user
        $tenant = Tenant::factory()->create([
            'type' => 'organization',
            'status' => 'pending_verification',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => null,
        ]);

        // Trigger email verification event
        $event = new Verified($user);
        $listener = new CreateTrialSubscription();
        $listener->handle($event);

        // Assert that exactly 2 agents were assigned (plan limit)
        $this->assertEquals(2, TenantAgent::where('tenant_id', $tenant->id)->count());

        // Assert agents are active
        $this->assertDatabaseHas('tenant_agents', [
            'tenant_id' => $tenant->id,
            'agent_id' => $agent1->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('tenant_agents', [
            'tenant_id' => $tenant->id,
            'agent_id' => $agent2->id,
            'status' => 'active',
        ]);

        // Assert third agent was NOT assigned (exceeded limit)
        $this->assertDatabaseMissing('tenant_agents', [
            'tenant_id' => $tenant->id,
            'agent_id' => $agent3->id,
        ]);
    }

    /**
     * Test that plan with zero max_agents doesn't assign any agents
     */
    public function test_plan_with_zero_agents_skips_assignment()
    {
        // Create free agents
        Agent::factory()->count(3)->create([
            'price_model' => 'free',
            'is_active' => true,
            'is_featured' => true,
        ]);

        // Create a plan that allows 0 agents
        $plan = SubscriptionPlan::factory()->create([
            'name' => 'Viewer Plan',
            'type' => 'organization',
            'tier' => 'free',
            'max_agents' => 0,
            'trial_days' => 7,
            'is_active' => true,
            'is_published' => true,
            'is_default' => true,
        ]);

        // Create tenant and user
        $tenant = Tenant::factory()->create([
            'type' => 'organization',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        // Trigger event
        $event = new Verified($user);
        $listener = new CreateTrialSubscription();
        $listener->handle($event);

        // Assert no agents were assigned
        $this->assertEquals(0, TenantAgent::where('tenant_id', $tenant->id)->count());
    }

    /**
     * Test that featured agents are prioritized
     */
    public function test_featured_agents_are_prioritized()
    {
        // Create non-featured agent (created first, oldest)
        $oldAgent = Agent::factory()->create([
            'name' => 'Old Agent',
            'price_model' => 'free',
            'is_active' => true,
            'is_featured' => false,
            'created_at' => now()->subDays(10),
        ]);

        // Create featured agent (created later, but featured)
        $featuredAgent = Agent::factory()->create([
            'name' => 'Featured Agent',
            'price_model' => 'free',
            'is_active' => true,
            'is_featured' => true,
            'created_at' => now()->subDays(5),
        ]);

        // Create plan allowing 1 agent
        $plan = SubscriptionPlan::factory()->create([
            'tier' => 'free',
            'max_agents' => 1,
            'is_default' => true,
        ]);

        // Create tenant and user
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        // Trigger event
        $event = new Verified($user);
        $listener = new CreateTrialSubscription();
        $listener->handle($event);

        // Assert featured agent was assigned, not the old one
        $this->assertDatabaseHas('tenant_agents', [
            'tenant_id' => $tenant->id,
            'agent_id' => $featuredAgent->id,
        ]);

        $this->assertDatabaseMissing('tenant_agents', [
            'tenant_id' => $tenant->id,
            'agent_id' => $oldAgent->id,
        ]);
    }

    /**
     * Test that agent assignment is idempotent (doesn't duplicate)
     */
    public function test_agent_assignment_is_idempotent()
    {
        // Create agent
        $agent = Agent::factory()->create([
            'price_model' => 'free',
            'is_active' => true,
            'is_featured' => true,
        ]);

        // Create plan
        $plan = SubscriptionPlan::factory()->create([
            'tier' => 'free',
            'max_agents' => 1,
            'is_default' => true,
        ]);

        // Create tenant and user
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        // Manually assign agent first
        TenantAgent::create([
            'tenant_id' => $tenant->id,
            'agent_id' => $agent->id,
            'status' => 'active',
            'purchased_at' => now(),
            'activated_at' => now(),
        ]);

        // Trigger event (should not duplicate)
        $event = new Verified($user);
        $listener = new CreateTrialSubscription();
        $listener->handle($event);

        // Assert still only 1 assignment
        $this->assertEquals(1, TenantAgent::where('tenant_id', $tenant->id)->count());
    }

    /**
     * Test metadata is correctly set on assigned agents
     */
    public function test_assigned_agents_have_correct_metadata()
    {
        // Create agent
        $agent = Agent::factory()->create([
            'name' => 'Test Agent',
            'price_model' => 'free',
            'is_active' => true,
            'is_featured' => true,
        ]);

        // Create plan
        $plan = SubscriptionPlan::factory()->create([
            'name' => 'Free Starter',
            'tier' => 'free',
            'max_agents' => 1,
            'is_default' => true,
        ]);

        // Create tenant and user
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        // Trigger event
        $event = new Verified($user);
        $listener = new CreateTrialSubscription();
        $listener->handle($event);

        // Get the assigned agent
        $tenantAgent = TenantAgent::where('tenant_id', $tenant->id)->first();

        // Assert metadata
        $this->assertNotNull($tenantAgent);
        $this->assertEquals('active', $tenantAgent->status);
        $this->assertNotNull($tenantAgent->purchased_at);
        $this->assertNotNull($tenantAgent->activated_at);

        // Check configuration
        $config = $tenantAgent->configuration;
        $this->assertEquals('auto_assignment', $config['assigned_via']);
        $this->assertEquals('Free Starter', $config['plan_name']);

        // Check metadata
        $metadata = $tenantAgent->metadata;
        $this->assertTrue($metadata['is_default_agent']);
        $this->assertEquals('Test Agent', $metadata['agent_name']);
    }
}
