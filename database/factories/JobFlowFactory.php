<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\JobFlow;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobFlowFactory extends Factory
{
    protected $model = JobFlow::class;

    public function definition(): array
    {
        return [
            'tenant_id' => tenant('id'),
            'agent_id' => Agent::factory(),
            'job_title' => fake()->jobTitle(),
            'job_description' => fake()->paragraph(),
            'organization_id' => Organization::factory(),
            'employment_type' => fake()->randomElement(['full-time', 'part-time', 'on-demand']),
            'schedule_type' => fake()->randomElement(['one-time', 'daily', 'weekly', 'monthly']),
            'schedule_config' => [
                'time' => '09:00',
                'timezone' => 'UTC',
            ],
            'input_source' => [
                'type' => 'api',
                'endpoint' => fake()->url(),
            ],
            'output_destination' => [
                'type' => 'database',
                'table' => 'results',
            ],
            'hitl_mode' => 'fully-ai',
            'hitl_rules' => [],
            'status' => 'draft',
            'total_runs' => 0,
            'successful_runs' => 0,
            'failed_runs' => 0,
        ];
    }
}
