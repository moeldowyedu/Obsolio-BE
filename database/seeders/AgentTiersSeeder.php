<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgentTiersSeeder extends Seeder
{
    public function run()
    {
        // Clear existing
        DB::table('agent_tiers')->truncate();

        // Insert tiers
        DB::table('agent_tiers')->insert([
            [
                'id' => 1,
                'name' => 'Basic',
                'description' => 'Simple, repetitive tasks with low AI cost',
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Professional',
                'description' => 'Medium complexity tasks requiring analysis',
                'display_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Specialized',
                'description' => 'Complex, industry-specific expert tasks',
                'display_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'Enterprise',
                'description' => 'Custom solutions with fine-tuned models',
                'display_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->command->info('✅ Agent tiers seeded successfully!');
    }
}
