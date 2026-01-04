<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgentPricingSeeder extends Seeder
{
    public function run()
    {
        $pricing = [];

        // Get all agents grouped by tier
        $agents = Agent::whereNotNull('tier_id')->orderBy('tier_id')->get();

        foreach ($agents as $agent) {
            $pricingData = match ($agent->tier_id) {
                1 => [ // Basic
                    'monthly_price' => 10.00,
                    'price_per_task' => 0.01,
                    'included_tasks_per_month' => 500,
                ],
                2 => [ // Professional
                    'monthly_price' => 29.00,
                    'price_per_task' => 0.02,
                    'included_tasks_per_month' => 1000,
                ],
                3 => [ // Specialized
                    'monthly_price' => 79.00,
                    'price_per_task' => 0.10,
                    'included_tasks_per_month' => 500,
                ],
                4 => [ // Enterprise
                    'monthly_price' => 199.00,
                    'price_per_task' => 0.20,
                    'included_tasks_per_month' => 1000,
                ],
                default => null,
            };

            if ($pricingData) {
                $pricing[] = [
                    'agent_id' => $agent->id,
                    'tier_id' => $agent->tier_id,
                    'monthly_price' => $pricingData['monthly_price'],
                    'price_per_task' => $pricingData['price_per_task'],
                    'included_tasks_per_month' => $pricingData['included_tasks_per_month'],
                    'is_active' => true,
                    'effective_from' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('agent_pricing')->insert($pricing);

        $this->command->info('✅ Agent pricing seeded successfully!');
        $this->command->info('   - Total pricing records: ' . count($pricing));
    }
}
