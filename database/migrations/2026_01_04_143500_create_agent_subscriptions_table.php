<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('agent_subscriptions', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->onDelete('cascade');

            $table->foreignUuid('agent_id')
                ->constrained('agents')
                ->onDelete('cascade');

            // Pricing
            $table->decimal('monthly_price', 10, 2)
                ->comment('Price locked at subscription time');

            // Status
            $table->string('status', 20)
                ->default('active')
                ->comment('active, paused, cancelled');

            // Billing Period
            $table->timestamp('started_at')
                ->useCurrent()
                ->comment('When agent subscription started');

            $table->date('current_period_start')
                ->comment('Start of current billing month');

            $table->date('current_period_end')
                ->comment('End of current billing month');

            $table->date('next_billing_date')
                ->comment('Next renewal date');

            // Cancellation
            $table->timestamp('cancelled_at')->nullable();

            // Settings
            $table->boolean('auto_renew')
                ->default(true)
                ->comment('Auto-renew monthly');

            $table->timestamps();

            // Indexes
            $table->index('tenant_id');
            $table->index('agent_id');
            $table->index('status');
            $table->index('next_billing_date');
            $table->index(['tenant_id', 'status']);

            // Unique constraint - one subscription per agent per tenant
            $table->unique(['tenant_id', 'agent_id'], 'tenant_agent_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('agent_subscriptions');
    }
};
