<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Engine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => tenant('id'),
            'created_by_user_id' => User::factory(),
            'name' => fake()->words(3, true) . ' Agent',
            'description' => fake()->sentence(),
            'type' => 'custom',
            'engines_used' => [Engine::factory()->create()->id],
            'config' => [
                'system_prompt' => 'You are a helpful assistant',
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ],
            'input_schema' => [],
            'output_schema' => [],
            'rubric_config' => [],
            'status' => 'draft',
            'is_published' => false,
            'version' => 1,
        ];
    }
}
