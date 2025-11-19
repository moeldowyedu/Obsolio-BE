<?php

namespace Tests\Unit\Models;

use App\Models\Agent;
use App\Models\Department;
use App\Models\JobFlow;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobFlowTest extends TestCase
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

    public function test_job_flow_schedule_config_is_cast_to_array(): void
    {
        $scheduleConfig = [
            'time' => '09:00',
            'timezone' => 'UTC',
            'days' => ['Monday', 'Wednesday', 'Friday'],
        ];

        $jobFlow = JobFlow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'schedule_config' => $scheduleConfig,
        ]);

        $this->assertIsArray($jobFlow->schedule_config);
        $this->assertEquals('09:00', $jobFlow->schedule_config['time']);
    }

    public function test_job_flow_has_agent_relationship(): void
    {
        $agent = Agent::factory()->create(['tenant_id' => $this->tenant->id]);
        $jobFlow = JobFlow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => $agent->id,
        ]);

        $this->assertInstanceOf(Agent::class, $jobFlow->agent);
        $this->assertEquals($agent->id, $jobFlow->agent->id);
    }

    public function test_job_flow_has_organization_relationship(): void
    {
        $organization = Organization::factory()->create(['tenant_id' => $this->tenant->id]);
        $jobFlow = JobFlow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'organization_id' => $organization->id,
        ]);

        $this->assertInstanceOf(Organization::class, $jobFlow->organization);
    }

    public function test_job_flow_has_hitl_supervisor_relationship(): void
    {
        $supervisor = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $jobFlow = JobFlow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hitl_supervisor_id' => $supervisor->id,
        ]);

        $this->assertInstanceOf(User::class, $jobFlow->hitlSupervisor);
        $this->assertEquals($supervisor->id, $jobFlow->hitlSupervisor->id);
    }

    public function test_job_flow_tracks_run_statistics(): void
    {
        $jobFlow = JobFlow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'total_runs' => 10,
            'successful_runs' => 8,
            'failed_runs' => 2,
        ]);

        $this->assertEquals(10, $jobFlow->total_runs);
        $this->assertEquals(8, $jobFlow->successful_runs);
        $this->assertEquals(2, $jobFlow->failed_runs);
    }
}
