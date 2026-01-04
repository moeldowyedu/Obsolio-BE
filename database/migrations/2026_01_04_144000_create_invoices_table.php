<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->onDelete('cascade');

            $table->foreignId('subscription_id')
                ->nullable()
                ->constrained('subscriptions')
                ->onDelete('set null');

            // Invoice Details
            $table->string('invoice_number', 50)
                ->unique()
                ->comment('e.g., INV-2025-01-0001');

            // Amount Breakdown
            $table->decimal('base_subscription_amount', 10, 2)
                ->default(0)
                ->comment('Base plan cost');

            $table->decimal('agent_addons_amount', 10, 2)
                ->default(0)
                ->comment('Total agent add-on costs');

            $table->decimal('usage_overage_amount', 10, 2)
                ->default(0)
                ->comment('Execution overage charges');

            $table->decimal('discount_amount', 10, 2)
                ->default(0)
                ->comment('Any discounts applied');

            $table->decimal('tax_amount', 10, 2)
                ->default(0)
                ->comment('Tax (if applicable)');

            $table->decimal('total_amount', 10, 2)
                ->comment('Final total amount');

            // Status
            $table->string('status', 20)
                ->default('pending')
                ->comment('pending, paid, failed, refunded, cancelled');

            // Billing Period
            $table->date('billing_period_start')
                ->comment('Period start date');

            $table->date('billing_period_end')
                ->comment('Period end date');

            $table->date('due_date')
                ->comment('Payment due date');

            // Payment Tracking
            $table->timestamp('paid_at')->nullable();

            $table->string('payment_method', 50)
                ->nullable()
                ->comment('paymob, bank_transfer, etc.');

            $table->string('payment_transaction_id', 100)
                ->nullable()
                ->comment('Paymob transaction ID');

            // Additional Info
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('tenant_id');
            $table->index('subscription_id');
            $table->index('invoice_number');
            $table->index('status');
            $table->index('due_date');
            $table->index('billing_period_start');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'billing_period_start']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};
