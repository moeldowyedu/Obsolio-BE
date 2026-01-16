<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PricingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ==================================================
        // STEP 0: Clear existing data (safe truncate)
        // ==================================================

        $this->command->info('🗑️  Clearing existing pricing data...');

        // Check for existing subscriptions
        $subscriptionsCount = DB::table('subscriptions')->count();

        if ($subscriptionsCount > 0) {
            $this->command->warn("⚠️  Found {$subscriptionsCount} existing subscriptions in the database.");

            if (!$this->command->confirm('This will DELETE ALL SUBSCRIPTIONS. Are you sure you want to continue?', false)) {
                $this->command->error('❌ Seeding aborted. Subscriptions were not deleted.');
                return;
            }

            $this->command->info('Deleting subscriptions...');
            DB::table('subscriptions')->delete();
            $this->command->info("✅ Deleted {$subscriptionsCount} subscriptions");
        }

        // Delete in correct order (child tables first, then parent)
        // 1. Delete subscription_plans (child of billing_cycles)
        $plansCount = DB::table('subscription_plans')->count();
        if ($plansCount > 0) {
            DB::table('subscription_plans')->delete();
            $this->command->info("✅ Deleted {$plansCount} subscription plans");
        }

        // 2. Delete billing_cycles (parent table)
        $cyclesCount = DB::table('billing_cycles')->count();
        if ($cyclesCount > 0) {
            DB::table('billing_cycles')->delete();
            $this->command->info("✅ Deleted {$cyclesCount} billing cycles");
        }

        // Reset auto-increment for billing_cycles
        DB::statement('ALTER SEQUENCE billing_cycles_id_seq RESTART WITH 1');

        $this->command->info('✅ Existing data cleared successfully');

        // ==================================================
        // STEP 1: Create Billing Cycles
        // ==================================================

        $billingCycles = [
            [
                'code' => 'monthly',
                'name' => 'Monthly',
                'months' => 1,
                'discount_percentage' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'annual',
                'name' => 'Annual',
                'months' => 12,
                'discount_percentage' => 17.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('billing_cycles')->insert($billingCycles);

        // Get billing cycle IDs
        $monthlyId = DB::table('billing_cycles')->where('code', 'monthly')->value('id');
        $annualId = DB::table('billing_cycles')->where('code', 'annual')->value('id');

        // ==================================================
        // STEP 2: Create Subscription Plans
        // ==================================================

        $plans = [
            // ============================================
            // STARTER PLAN - Monthly
            // ============================================
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Starter',
                'description' => 'Perfect for individuals and small teams getting started with AI agents',
                'type' => 'organization',
                'tier' => 'pro',
                'billing_cycle_id' => $monthlyId,
                'base_price' => 29.00,
                'final_price' => 29.00,
                'price_monthly' => 29.00,
                'price_annual' => null,
                'included_executions' => 1000,
                'overage_price_per_execution' => 0.0100,
                'max_users' => 3,
                'max_agents' => 5,
                'max_agent_slots' => 5,
                'max_basic_agents' => 5,
                'max_professional_agents' => 0,
                'max_specialized_agents' => 0,
                'max_enterprise_agents' => 0,
                'storage_gb' => 10,
                'trial_days' => 14,
                'features' => json_encode([
                    '1,000 agent executions per month',
                    'Up to 5 Basic AI agents',
                    '10GB storage',
                    'Email support (48h response)',
                    'Basic analytics dashboard',
                    'API access',
                    'Community support',
                ]),
                'highlight_features' => json_encode([
                    'Perfect for individuals',
                    '14-day free trial',
                ]),
                'limits' => json_encode([
                    'max_concurrent_executions' => 3,
                    'max_file_upload_mb' => 50,
                    'api_rate_limit_per_minute' => 60,
                ]),
                'is_active' => true,
                'is_published' => true,
                'is_archived' => false,
                'is_default' => false,
                'display_order' => 1,
                'plan_version' => '1.0',
                'parent_plan_id' => null,
                'metadata' => json_encode([
                    'recommended_for' => 'individuals',
                    'color' => '#3B82F6',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // ============================================
            // STARTER PLAN - Annual
            // ============================================
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Starter',
                'description' => 'Perfect for individuals and small teams getting started with AI agents',
                'type' => 'organization',
                'tier' => 'pro',
                'billing_cycle_id' => $annualId,
                'base_price' => 348.00, // 29 * 12 = 348
                'final_price' => 288.84, // 348 - 17% = 288.84 (24.07/month)
                'price_monthly' => null,
                'price_annual' => 288.84,
                'included_executions' => 1000,
                'overage_price_per_execution' => 0.0100,
                'max_users' => 3,
                'max_agents' => 5,
                'max_agent_slots' => 5,
                'max_basic_agents' => 5,
                'max_professional_agents' => 0,
                'max_specialized_agents' => 0,
                'max_enterprise_agents' => 0,
                'storage_gb' => 10,
                'trial_days' => 14,
                'features' => json_encode([
                    '1,000 agent executions per month',
                    'Up to 5 Basic AI agents',
                    '10GB storage',
                    'Email support (48h response)',
                    'Basic analytics dashboard',
                    'API access',
                    'Community support',
                ]),
                'highlight_features' => json_encode([
                    'Save 17% with annual billing',
                    '14-day free trial',
                ]),
                'limits' => json_encode([
                    'max_concurrent_executions' => 3,
                    'max_file_upload_mb' => 50,
                    'api_rate_limit_per_minute' => 60,
                ]),
                'is_active' => true,
                'is_published' => true,
                'is_archived' => false,
                'is_default' => false,
                'display_order' => 1,
                'plan_version' => '1.0',
                'parent_plan_id' => null,
                'metadata' => json_encode([
                    'recommended_for' => 'individuals',
                    'color' => '#3B82F6',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // ============================================
            // PROFESSIONAL PLAN - Monthly
            // ============================================
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Professional',
                'description' => 'For growing teams that need more power and flexibility',
                'type' => 'organization',
                'tier' => 'team',
                'billing_cycle_id' => $monthlyId,
                'base_price' => 99.00,
                'final_price' => 99.00,
                'price_monthly' => 99.00,
                'price_annual' => null,
                'included_executions' => 5000,
                'overage_price_per_execution' => 0.0080,
                'max_users' => 10,
                'max_agents' => 20,
                'max_agent_slots' => 20,
                'max_basic_agents' => 20,
                'max_professional_agents' => 5,
                'max_specialized_agents' => 0,
                'max_enterprise_agents' => 0,
                'storage_gb' => 50,
                'trial_days' => 14,
                'features' => json_encode([
                    '5,000 agent executions per month',
                    'Up to 20 agents (including 5 Professional tier)',
                    '50GB storage',
                    'Priority email support (24h response)',
                    'Advanced analytics & reporting',
                    'Custom integrations',
                    'Team collaboration tools',
                    'Version control for agents',
                    'Webhook notifications',
                    'Advanced API access',
                ]),
                'highlight_features' => json_encode([
                    'Most popular',
                    'Best for growing teams',
                ]),
                'limits' => json_encode([
                    'max_concurrent_executions' => 10,
                    'max_file_upload_mb' => 200,
                    'api_rate_limit_per_minute' => 300,
                ]),
                'is_active' => true,
                'is_published' => true,
                'is_archived' => false,
                'is_default' => true, // This is the default/recommended plan
                'display_order' => 2,
                'plan_version' => '1.0',
                'parent_plan_id' => null,
                'metadata' => json_encode([
                    'recommended_for' => 'teams',
                    'color' => '#8B5CF6',
                    'is_popular' => true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // ============================================
            // PROFESSIONAL PLAN - Annual
            // ============================================
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Professional',
                'description' => 'For growing teams that need more power and flexibility',
                'type' => 'organization',
                'tier' => 'team',
                'billing_cycle_id' => $annualId,
                'base_price' => 1188.00, // 99 * 12 = 1188
                'final_price' => 986.04, // 1188 - 17% = 986.04 (82.17/month)
                'price_monthly' => null,
                'price_annual' => 986.04,
                'included_executions' => 5000,
                'overage_price_per_execution' => 0.0080,
                'max_users' => 10,
                'max_agents' => 20,
                'max_agent_slots' => 20,
                'max_basic_agents' => 20,
                'max_professional_agents' => 5,
                'max_specialized_agents' => 0,
                'max_enterprise_agents' => 0,
                'storage_gb' => 50,
                'trial_days' => 14,
                'features' => json_encode([
                    '5,000 agent executions per month',
                    'Up to 20 agents (including 5 Professional tier)',
                    '50GB storage',
                    'Priority email support (24h response)',
                    'Advanced analytics & reporting',
                    'Custom integrations',
                    'Team collaboration tools',
                    'Version control for agents',
                    'Webhook notifications',
                    'Advanced API access',
                ]),
                'highlight_features' => json_encode([
                    'Save 17% with annual billing',
                    'Most popular',
                ]),
                'limits' => json_encode([
                    'max_concurrent_executions' => 10,
                    'max_file_upload_mb' => 200,
                    'api_rate_limit_per_minute' => 300,
                ]),
                'is_active' => true,
                'is_published' => true,
                'is_archived' => false,
                'is_default' => false,
                'display_order' => 2,
                'plan_version' => '1.0',
                'parent_plan_id' => null,
                'metadata' => json_encode([
                    'recommended_for' => 'teams',
                    'color' => '#8B5CF6',
                    'is_popular' => true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // ============================================
            // ENTERPRISE PLAN - Monthly
            // ============================================
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Enterprise',
                'description' => 'For large organizations with advanced needs and dedicated support',
                'type' => 'organization',
                'tier' => 'enterprise',
                'billing_cycle_id' => $monthlyId,
                'base_price' => 499.00,
                'final_price' => 499.00,
                'price_monthly' => 499.00,
                'price_annual' => null,
                'included_executions' => 50000,
                'overage_price_per_execution' => 0.0050,
                'max_users' => 999999, // Unlimited (using large number)
                'max_agents' => 999999, // Unlimited (using large number)
                'max_agent_slots' => 999999, // Unlimited (using large number)
                'max_basic_agents' => 999999, // Unlimited (using large number)
                'max_professional_agents' => 999999, // Unlimited (using large number)
                'max_specialized_agents' => 999999, // Unlimited (using large number)
                'max_enterprise_agents' => 999999, // Unlimited (using large number)
                'storage_gb' => 999999, // Unlimited (using large number)
                'trial_days' => 30, // Enterprise gets 30-day trial
                'features' => json_encode([
                    '50,000+ agent executions per month',
                    'Unlimited agents (all tiers)',
                    'Unlimited storage',
                    '24/7 dedicated phone & email support',
                    'Advanced analytics & custom reports',
                    'Custom integrations & webhooks',
                    'SSO (SAML, OAuth)',
                    'Advanced security & compliance',
                    'Dedicated account manager',
                    'SLA guarantee (99.9% uptime)',
                    'Priority feature requests',
                    'White-label options',
                    'Custom training & onboarding',
                    'Dedicated infrastructure (optional)',
                ]),
                'highlight_features' => json_encode([
                    'Unlimited everything',
                    '30-day trial',
                    'Dedicated support',
                ]),
                'limits' => json_encode([
                    'max_concurrent_executions' => null, // Unlimited
                    'max_file_upload_mb' => null, // Unlimited
                    'api_rate_limit_per_minute' => null, // Unlimited
                ]),
                'is_active' => true,
                'is_published' => true,
                'is_archived' => false,
                'is_default' => false,
                'display_order' => 3,
                'plan_version' => '1.0',
                'parent_plan_id' => null,
                'metadata' => json_encode([
                    'recommended_for' => 'enterprises',
                    'color' => '#10B981',
                    'contact_sales' => true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // ============================================
            // ENTERPRISE PLAN - Annual
            // ============================================
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Enterprise',
                'description' => 'For large organizations with advanced needs and dedicated support',
                'type' => 'organization',
                'tier' => 'enterprise',
                'billing_cycle_id' => $annualId,
                'base_price' => 5988.00, // 499 * 12 = 5988
                'final_price' => 4970.04, // 5988 - 17% = 4970.04 (414.17/month)
                'price_monthly' => null,
                'price_annual' => 4970.04,
                'included_executions' => 50000,
                'overage_price_per_execution' => 0.0050,
                'max_users' => 999999, // Unlimited (using large number)
                'max_agents' => 999999, // Unlimited (using large number)
                'max_agent_slots' => 999999, // Unlimited (using large number)
                'max_basic_agents' => 999999, // Unlimited (using large number)
                'max_professional_agents' => 999999, // Unlimited (using large number)
                'max_specialized_agents' => 999999, // Unlimited (using large number)
                'max_enterprise_agents' => 999999, // Unlimited (using large number)
                'storage_gb' => 999999, // Unlimited (using large number)
                'trial_days' => 30, // Enterprise gets 30-day trial
                'features' => json_encode([
                    '50,000+ agent executions per month',
                    'Unlimited agents (all tiers)',
                    'Unlimited storage',
                    '24/7 dedicated phone & email support',
                    'Advanced analytics & custom reports',
                    'Custom integrations & webhooks',
                    'SSO (SAML, OAuth)',
                    'Advanced security & compliance',
                    'Dedicated account manager',
                    'SLA guarantee (99.9% uptime)',
                    'Priority feature requests',
                    'White-label options',
                    'Custom training & onboarding',
                    'Dedicated infrastructure (optional)',
                ]),
                'highlight_features' => json_encode([
                    'Save 17% with annual billing',
                    'Unlimited everything',
                    '30-day trial',
                ]),
                'limits' => json_encode([
                    'max_concurrent_executions' => null, // Unlimited
                    'max_file_upload_mb' => null, // Unlimited
                    'api_rate_limit_per_minute' => null, // Unlimited
                ]),
                'is_active' => true,
                'is_published' => true,
                'is_archived' => false,
                'is_default' => false,
                'display_order' => 3,
                'plan_version' => '1.0',
                'parent_plan_id' => null,
                'metadata' => json_encode([
                    'recommended_for' => 'enterprises',
                    'color' => '#10B981',
                    'contact_sales' => true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('subscription_plans')->insert($plans);

        $this->command->info('✅ Pricing data seeded successfully!');
        $this->command->info('   - 2 billing cycles created (monthly, annual)');
        $this->command->info('   - 6 subscription plans created:');
        $this->command->info('     • Starter (Monthly & Annual) - 14-day trial');
        $this->command->info('     • Professional (Monthly & Annual) - 14-day trial');
        $this->command->info('     • Enterprise (Monthly & Annual) - 30-day trial');
    }
}
