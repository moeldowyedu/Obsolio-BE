<?php

namespace Database\Factories;

use App\Models\Engine;
use Illuminate\Database\Eloquent\Factories\Factory;

class EngineFactory extends Factory
{
    protected $model = Engine::class;

    public function definition(): array
    {
        return [
            'name' => 'GPT-4',
            'provider' => 'OpenAI',
            'model_name' => 'gpt-4-turbo',
            'version' => '1.0',
            'description' => 'Advanced language model',
            'capabilities' => ['text-generation', 'code-generation', 'analysis'],
            'input_types' => ['text', 'json'],
            'output_types' => ['text', 'json'],
            'pricing' => [
                'input_token_price' => 0.03,
                'output_token_price' => 0.06,
            ],
            'limits' => [
                'max_tokens' => 4096,
                'rate_limit' => 100,
            ],
            'config' => [],
            'is_active' => true,
        ];
    }
}
