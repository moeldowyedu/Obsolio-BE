<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->city() . ' Branch',
            'code' => strtoupper(fake()->lexify('???')),
            'location' => fake()->city(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'status' => 'active',
        ];
    }
}
