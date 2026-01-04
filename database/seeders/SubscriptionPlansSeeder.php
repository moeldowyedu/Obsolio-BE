<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlansSeeder extends Seeder
{
    public function run()
    {
        // Clear existing plans
        DB::table('subscription_plans')->truncate();

        $plans = [];

        // ========================================
        // FREE PLAN (Monthly only)
        // ========================================
        $plans[] = [
            'name' => 'Free',
            'slug' => 'free',
            'type' => 'organization',
            'tier' => 'free',
            'billing_cycle_id' => 1, // Monthly
            'base_price' => 0,
            'final_price' => 0,
            'included_executions' => 500,
            'overage_price_per_execution' => null, // No overage allowed
            'max_agent_slots' => 2,
            'max_basic_agents' => 2,
            'max_professional_agents' => 0,
            'max_specialized_agents' => 0,
            'max_enterprise_agents' => 0,
            'is_active' => true,
            'is_published' => true,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // ========================================
        // STARTER PLAN (3 billing cycles)
        // ========================================

        // Starter - Monthly
        $plans[] = [
            'name' => 'Starter',
            'slug' => 'starter-monthly',
            'type' => 'organization',
            'tier' => 'starter',
            'billing_cycle_id' => 1, // Monthly
            'base_price' => 49.00,
            'final_price' => 49.00,
            'included_executions' => 2000,
            'overage_price_per_execution' => 0.015,
            'max_agent_slots' => 4,
            'max_basic_agents' => 999, // Unlimited
            'max_professional_agents' => 2,
            'max_specialized_agents' => 0,
            'max_enterprise_agents' => 0,
            'is_active' => true,
            'is_published' => true,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Starter - Semi-Annual
        $plans[] = [
            'name' => 'Starter',
            'slug' => 'starter-semi-annual',
            'type' => 'organization',
            'tier' => 'starter',
            'billing_cycle_id' => 2, // Semi-Annual
            'base_price' => 294.00, // $49 × 6
            'final_price' => 259.00, // 12% discount
            'included_executions' => 2000,
            'overage_price_per_execution' => 0.015,
            'max_agent_slots' => 4,
            'max_basic_agents' => 999,
            'max_professional_agents' => 2,
            'max_specialized_agents' => 0,
            'max_enterprise_agents' => 0,
            'is_active' => true,
            'is_published' => true,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Starter - Annual
        $plans[] = [
            'name' => 'Starter',
            'slug' => 'starter-annual',
            'type' => 'organization',
            'tier' => 'starter',
            'billing_cycle_id' => 3, // Annual
            'base_price' => 588.00, // $49 × 12
            'final_price' => 470.00, // 20% discount
            'included_executions' => 2000,
            'overage_price_per_execution' => 0.015,
            'max_agent_slots' => 4,
            'max_basic_agents' => 999,
            'max_professional_agents' => 2,
            'max_specialized_agents' => 0,
            'max_enterprise_agents' => 0,
            'is_active' => true,
            'is_published' => true,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // ========================================
        // PROFESSIONAL PLAN (3 billing cycles)
        // ========================================

        // Pro - Monthly
        $plans[] = [
            'name' => 'Professional',
            'slug' => 'professional-monthly',
            'type' => 'organization',
            'tier' => 'professional',
            'billing_cycle_id' => 1,
            'base_price' => 199.00,
            'final_price' => 199.00,
            'included_executions' => 6000,
            'overage_price_per_execution' => 0.01,
            'max_agent_slots' => 10,
            'max_basic_agents' => 999,
            'max_professional_agents' => 999,
            'max_specialized_agents' => 3,
            'max_enterprise_agents' => 0,
            'is_active' => true,
            'is_published' => true,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Pro - Semi-Annual
        $plans[] = [
            'name' => 'Professional',
            'slug' => 'professional-semi-annual',
            'type' => 'organization',
            'tier' => 'professional',
            'billing_cycle_id' => 2,
            'base_price' => 1194.00, // $199 × 6
            'final_price' => 1075.00, // 10% discount
            'included_executions' => 6000,
            'overage_price_per_execution' => 0.01,
            'max_agent_slots' => 10,
            'max_basic_agents' => 999,
            'max_professional_agents' => 999,
            'max_specialized_agents' => 3,
            'max_enterprise_agents' => 0,
            'is_active' => true,
            'is_published' => true,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Pro - Annual
        $plans[] = [
            'name' => 'Professional',
            'slug' => 'professional-annual',
            'type' => 'organization',
            'tier' => 'professional',
            'billing_cycle_id' => 3,
            'base_price' => 2388.00, // $199 × 12
            'final_price' => 1910.00, // 20% discount
            'included_executions' => 6000,
            'overage_price_per_execution' => 0.01,
            'max_agent_slots' => 10,
            'max_basic_agents' => 999,
            'max_professional_agents' => 999,
            'max_specialized_agents' => 3,
            'max_enterprise_agents' => 0,
            'is_active' => true,
            'is_published' => true,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // ========================================
        // BUSINESS PLAN (3 billing cycles)
        // ========================================

        // Business - Monthly
        $plans[] = [
            'name' => 'Business',
            'slug' => 'business-monthly',
            'type' => 'organization',
            'tier' => 'business',
            'billing_cycle_id' => 1,
            'base_price' => 499.00,
            'final_price' => 499.00,
            'included_executions' => 35000,
            'overage_price_per_execution' => 0.008,
            'max_agent_slots' => 25,
            'max_basic_agents' => 999,
            'max_professional_agents' => 999,
            'max_specialized_agents' => 999,
            'max_enterprise_agents' => 3,
            'is_active' => true,
            'is_published' => true,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Business - Semi-Annual
        $plans[] = [
            'name' => 'Business',
            'slug' => 'business-semi-annual',
            'type' => 'organization',
            'tier' => 'business',
            'billing_cycle_id' => 2,
            'base_price' => 2994.00, // $499 × 6
            'final_price' => 2695.00, // 10% discount
            'included_executions' => 35000,
            'overage_price_per_execution' => 0.008,
            'max_agent_slots' => 25,
            'max_basic_agents' => 999,
            'max_professional_agents' => 999,
            'max_specialized_agents' => 999,
            'max_enterprise_agents' => 3,
            'is_active' => true,
            'is_published' => true,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Business - Annual
        $plans[] = [
            'name' => 'Business',
            'slug' => 'business-annual',
            'type' => 'organization',
            'tier' => 'business',
            'billing_cycle_id' => 3,
            'base_price' => 5988.00, // $499 × 12
            'final_price' => 4790.00, // 20% discount
            'included_executions' => 35000,
            'overage_price_per_execution' => 0.008,
            'max_agent_slots' => 25,
            'max_basic_agents' => 999,
            'max_professional_agents' => 999,
            'max_specialized_agents' => 999,
            'max_enterprise_agents' => 3,
            'is_active' => true,
            'is_published' => true,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Insert all plans
        DB::table('subscription_plans')->insert($plans);

        $this->command->info('✅ Subscription plans seeded successfully!');
        $this->command->info('   - Total plans: ' . count($plans));
        $this->command->info('   - Free: 1 plan');
        $this->command->info('   - Starter: 3 plans (Monthly, Semi-Annual, Annual)');
        $this->command->info('   - Professional: 3 plans (Monthly, Semi-Annual, Annual)');
        $this->command->info('   - Business: 3 plans (Monthly, Semi-Annual, Annual)');
    }
}
