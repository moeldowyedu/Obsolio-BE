<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            // ==================== PERSONAL PLANS ====================
            [
                'id' => Str::uuid(),
                'name' => 'Free Personal',
                'type' => 'personal',
                'tier' => 'free',
                'price_monthly' => 0,
                'price_annual' => 0,
                'max_users' => 1,
                'max_agents' => 2,
                'storage_gb' => 1,
                'trial_days' => 0,
                'is_active' => true,
                'is_default' => true,
                'description' => 'Perfect for trying out OBSOLIO',
                'features' => [
                    'Basic AI Agents',
                    'Email Support',
                    '1GB Storage',
                    'Community Access',
                ],
                'limits' => [
                    'agents_per_month' => 100,
                    'api_calls_per_day' => 50,
                ]
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Pro Personal',
                'type' => 'personal',
                'tier' => 'pro',
                'price_monthly' => 29.00,
                'price_annual' => 290.00, // 2 months free
                'max_users' => 1,
                'max_agents' => 10,
                'storage_gb' => 10,
                'trial_days' => 14,
                'is_active' => true,
                'description' => 'For power users and freelancers',
                'features' => [
                    'All Free features',
                    'Advanced AI Agents',
                    'Priority Email Support',
                    '10GB Storage',
                    'Custom Agent Training',
                    'API Access',
                ],
                'limits' => [
                    'agents_per_month' => 1000,
                    'api_calls_per_day' => 500,
                ]
            ],

            // ==================== ORGANIZATION PLANS ====================
            [
                'id' => Str::uuid(),
                'name' => 'Team',
                'type' => 'organization',
                'tier' => 'team',
                'price_monthly' => 99.00,
                'price_annual' => 990.00, // 2 months free
                'max_users' => 10,
                'max_agents' => 25,
                'storage_gb' => 50,
                'trial_days' => 14,
                'is_active' => true,
                'is_default' => true,
                'description' => 'For small teams getting started',
                'features' => [
                    'All Pro features',
                    'Up to 10 team members',
                    'Team Collaboration',
                    'Shared Agent Library',
                    '50GB Storage',
                    'Phone Support',
                    'Advanced Analytics',
                ],
                'limits' => [
                    'agents_per_month' => 5000,
                    'api_calls_per_day' => 2000,
                ]
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Business',
                'type' => 'organization',
                'tier' => 'business',
                'price_monthly' => 299.00,
                'price_annual' => 2990.00, // 2 months free
                'max_users' => 50,
                'max_agents' => 100,
                'storage_gb' => 200,
                'trial_days' => 14,
                'is_active' => true,
                'description' => 'For growing businesses',
                'features' => [
                    'All Team features',
                    'Up to 50 team members',
                    'Advanced Permissions',
                    'Custom Integrations',
                    '200GB Storage',
                    'Priority Phone Support',
                    'Dedicated Account Manager',
                    'SLA Guarantee',
                    'Custom Agent Development',
                ],
                'limits' => [
                    'agents_per_month' => 20000,
                    'api_calls_per_day' => 10000,
                ]
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Enterprise',
                'type' => 'organization',
                'tier' => 'enterprise',
                'price_monthly' => null, // Custom pricing
                'price_annual' => null,
                'max_users' => 999999,
                'max_agents' => 999999,
                'storage_gb' => 999999,
                'trial_days' => 30,
                'is_active' => true,
                'description' => 'For large enterprises with custom needs',
                'features' => [
                    'All Business features',
                    'Unlimited team members',
                    'Unlimited Agents',
                    'Unlimited Storage',
                    'White-label Options',
                    'On-premise Deployment',
                    'Custom SLA',
                    '24/7 Priority Support',
                    'Dedicated Infrastructure',
                    'Advanced Security',
                    'Compliance Support',
                    'Custom Development',
                ],
                'limits' => [
                    'agents_per_month' => 999999,
                    'api_calls_per_day' => 999999,
                ]
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::firstOrCreate(
                ['type' => $plan['type'], 'tier' => $plan['tier']],
                $plan
            );
        }

        $this->command->info('✅ Subscription plans seeded successfully!');
    }
}