<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'tenant_id' => tenant('id'),
            'name' => fake()->company(),
            'industry' => fake()->randomElement(['Technology', 'Healthcare', 'Finance', 'Education', 'Manufacturing']),
            'company_size' => fake()->randomElement(['1-10', '11-50', '51-200', '201-500', '500+']),
            'country' => fake()->country(),
            'timezone' => fake()->timezone(),
            'description' => fake()->paragraph(),
            'settings' => [],
        ];
    }
}
