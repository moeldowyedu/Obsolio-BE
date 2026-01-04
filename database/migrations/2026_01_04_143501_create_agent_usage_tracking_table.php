<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('agent_usage_tracking', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->onDelete('cascade');

            $table->foreignUuid('agent_id')
                ->constrained('agents')
                ->onDelete('cascade');

            $table->uuid('execution_id')
                ->nullable()
                ->comment('Reference to agent_executions table');

            // Note: agent_executions foreign key will be added if table exists
            // Uncomment if agent_executions uses UUID primary key
            // $table->foreign('execution_id')
            //     ->references('id')
            //     ->on('agent_executions')
            //     ->onDelete('set null');

            // Execution Details
            $table->string('task_type', 100)->nullable();

            $table->integer('tokens_used')->nullable();

            $table->integer('execution_time_ms')
                ->nullable()
                ->comment('Execution time in milliseconds');

            // Cost Tracking
            $table->decimal('ai_model_cost', 10, 6)
                ->nullable()
                ->comment('Actual cost from OpenAI/Claude/Gemini');

            $table->decimal('charged_amount', 10, 4)
                ->nullable()
                ->comment('Amount charged to customer');

            // Metadata
            $table->jsonb('metadata')->nullable();

            // Timestamps
            $table->timestamp('executed_at')
                ->useCurrent()
                ->comment('When execution occurred');

            $table->date('billing_cycle_month')
                ->comment('Billing month (e.g., 2025-01-01 for January)');

            // Only created_at, no updated_at (immutable records)
            $table->timestamp('created_at')->useCurrent();

            // Indexes for fast queries
            $table->index('tenant_id');
            $table->index('agent_id');
            $table->index('execution_id');
            $table->index('executed_at');
            $table->index('billing_cycle_month');

            // Composite indexes for common queries
            $table->index(['tenant_id', 'billing_cycle_month'], 'tenant_month_idx');
            $table->index(['agent_id', 'billing_cycle_month'], 'agent_month_idx');
            $table->index(['tenant_id', 'agent_id', 'billing_cycle_month'], 'tenant_agent_month_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('agent_usage_tracking');
    }
};
