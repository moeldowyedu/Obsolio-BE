<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Only add columns that don't already exist

            // next_billing_date - NEW COLUMN
            if (!Schema::hasColumn('subscriptions', 'next_billing_date')) {
                $table->date('next_billing_date')
                    ->nullable()
                    ->after('current_period_end')
                    ->comment('Next billing/renewal date');
            }

            // auto_renew - NEW COLUMN
            if (!Schema::hasColumn('subscriptions', 'auto_renew')) {
                $table->boolean('auto_renew')
                    ->default(true)
                    ->after('next_billing_date')
                    ->comment('Whether subscription auto-renews');
            }

            // cancelled_at already exists, skip

            // cancellation_reason - NEW COLUMN
            if (!Schema::hasColumn('subscriptions', 'cancellation_reason')) {
                $table->string('cancellation_reason', 500)
                    ->nullable()
                    ->after('canceled_at')
                    ->comment('Reason for cancellation');
            }

            // execution_quota - NEW COLUMN
            if (!Schema::hasColumn('subscriptions', 'execution_quota')) {
                $table->integer('execution_quota')
                    ->default(0)
                    ->after('cancellation_reason')
                    ->comment('Executions included this period');
            }

            // executions_used - NEW COLUMN
            if (!Schema::hasColumn('subscriptions', 'executions_used')) {
                $table->integer('executions_used')
                    ->default(0)
                    ->after('execution_quota')
                    ->comment('Executions used this period');
            }

            // Indexes
            if (!Schema::hasColumn('subscriptions', 'next_billing_date')) {
                $table->index('next_billing_date');
            }
        });
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop indexes if they exist
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('subscriptions');

            if (isset($indexesFound['subscriptions_next_billing_date_index'])) {
                $table->dropIndex(['next_billing_date']);
            }

            // Drop only columns that were added by this migration
            $columnsToCheck = [
                'next_billing_date',
                'auto_renew',
                'cancellation_reason',
                'execution_quota',
                'executions_used',
            ];

            $columnsToDrop = [];
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
