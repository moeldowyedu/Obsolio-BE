<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Billing Cycle
            $table->foreignId('billing_cycle_id')
                ->nullable()
                ->after('id')
                ->constrained('billing_cycles')
                ->onDelete('set null');

            // Pricing
            $table->decimal('base_price', 10, 2)
                ->nullable()
                ->after('price_annual')
                ->comment('Base price before discount');

            $table->decimal('final_price', 10, 2)
                ->nullable()
                ->after('base_price')
                ->comment('Final price after discount');

            // Execution Limits
            $table->integer('included_executions')
                ->default(0)
                ->after('final_price')
                ->comment('Included execution quota per month');

            $table->decimal('overage_price_per_execution', 10, 4)
                ->nullable()
                ->after('included_executions')
                ->comment('Cost per extra execution beyond quota');

            // Agent Slot Limits
            $table->integer('max_agent_slots')
                ->nullable()
                ->after('overage_price_per_execution')
                ->comment('Maximum number of agent slots');

            $table->integer('max_basic_agents')
                ->nullable()
                ->after('max_agent_slots')
                ->comment('Max basic tier agents (999 = unlimited)');

            $table->integer('max_professional_agents')
                ->nullable()
                ->after('max_basic_agents')
                ->comment('Max professional tier agents');

            $table->integer('max_specialized_agents')
                ->nullable()
                ->after('max_professional_agents')
                ->comment('Max specialized tier agents');

            $table->integer('max_enterprise_agents')
                ->nullable()
                ->after('max_specialized_agents')
                ->comment('Max enterprise tier agents');

            // Indexes
            $table->index('billing_cycle_id');
        });
    }

    public function down()
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropForeign(['billing_cycle_id']);
            $table->dropIndex(['billing_cycle_id']);

            $table->dropColumn([
                'billing_cycle_id',
                'base_price',
                'final_price',
                'included_executions',
                'overage_price_per_execution',
                'max_agent_slots',
                'max_basic_agents',
                'max_professional_agents',
                'max_specialized_agents',
                'max_enterprise_agents',
            ]);
        });
    }
};
