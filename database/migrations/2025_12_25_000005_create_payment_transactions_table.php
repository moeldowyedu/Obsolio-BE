<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('subscription_id')->nullable();
            $table->uuid('invoice_id')->nullable();

            // Paymob transaction details
            $table->string('paymob_transaction_id')->nullable()->index();
            $table->string('paymob_order_id')->nullable()->index();
            $table->string('merchant_order_id')->unique();

            // Payment details
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EGP');
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'refunded',
                'cancelled'
            ])->default('pending');

            // Payment method details
            $table->string('payment_method')->nullable(); // card, wallet, etc
            $table->string('card_last_four')->nullable();
            $table->string('card_brand')->nullable();

            // Additional info
            $table->text('description')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->jsonb('paymob_response')->nullable();

            // Refund details
            $table->boolean('is_refunded')->default(false);
            $table->timestamp('refunded_at')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();

            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->onDelete('set null');

            $table->foreign('invoice_id')
                ->references('id')
                ->on('billing_invoices')
                ->onDelete('set null');

            // Indexes
            $table->index('tenant_id');
            $table->index('subscription_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
