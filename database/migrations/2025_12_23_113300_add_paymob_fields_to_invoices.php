<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->string('paymob_order_id')->nullable()->after('stripe_invoice_id');
            $table->string('paymob_transaction_id')->nullable()->after('paymob_order_id');
            $table->text('paymob_payment_key')->nullable()->after('paymob_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropColumn(['paymob_order_id', 'paymob_transaction_id', 'paymob_payment_key']);
        });
    }
};
