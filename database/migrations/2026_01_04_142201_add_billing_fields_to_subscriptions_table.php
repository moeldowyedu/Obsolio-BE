<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Billing Period Tracking
            $table->date('current_period_start')
                ->nullable()
                ->after('status')
                ->comment('Start date of current billing period');

            $table->date('current_period_end')
                ->nullable()
                ->after('current_period_start')
                ->comment('End date of current billing period');

            $table->date('next_billing_date')
                ->nullable()
                ->after('current_period_end')
                ->comment('Next billing/renewal date');

            // Cancellation Tracking
            $table->boolean('auto_renew')
                ->default(true)
                ->after('next_billing_date')
                ->comment('Whether subscription auto-renews');

            $table->timestamp('cancelled_at')
                ->nullable()
                ->after('auto_renew')
                ->comment('When subscription was cancelled');

            $table->string('cancellation_reason', 500)
                ->nullable()
                ->after('cancelled_at')
                ->comment('Reason for cancellation');

            // Usage Tracking
            $table->integer('execution_quota')
                ->default(0)
                ->after('cancellation_reason')
                ->comment('Executions included this period');

            $table->integer('executions_used')
                ->default(0)
                ->after('execution_quota')
                ->comment('Executions used this period');

            // Indexes
            $table->index('next_billing_date');
            $table->index('current_period_end');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['next_billing_date']);
            $table->dropIndex(['current_period_end']);
            $table->dropIndex(['tenant_id', 'status']);

            $table->dropColumn([
                'current_period_start',
                'current_period_end',
                'next_billing_date',
                'auto_renew',
                'cancelled_at',
                'cancellation_reason',
                'execution_quota',
                'executions_used',
            ]);
        });
    }
};
