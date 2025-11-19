<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentExecution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentExecutionFactory extends Factory
{
    protected $model = AgentExecution::class;

    public function definition(): array
    {
        return [
            'tenant_id' => tenant('id'),
            'agent_id' => Agent::factory(),
            'triggered_by_user_id' => User::factory(),
            'input_data' => ['message' => fake()->sentence()],
            'output_data' => ['response' => fake()->paragraph()],
            'status' => fake()->randomElement(['pending', 'running', 'completed', 'failed']),
            'execution_time_ms' => fake()->numberBetween(100, 5000),
            'tokens_used' => fake()->numberBetween(50, 1000),
            'cost' => fake()->randomFloat(4, 0.01, 1.00),
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ];
    }
}
