<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        // 2. Organizations table
        Schema::table('organizations', function (Blueprint $table) {
            // Drop constraint using the standard naming convention first
            $table->dropForeign(['tenant_id']);

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        // 3. Tenant Memberships
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        // 4. Impersonation Logs (if exists)
        if (Schema::hasTable('impersonation_logs')) {
            Schema::table('impersonation_logs', function (Blueprint $table) {
                // Determine existing FK name or guess
                $table->dropForeign(['impersonated_tenant_id']);

                $table->foreign('impersonated_tenant_id')
                    ->references('id')->on('tenants')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            });
        }

        // 5. User Activities (if exists)
        if (Schema::hasTable('user_activities')) {
            Schema::table('user_activities', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->foreign('tenant_id')
                    ->references('id')->on('tenants')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            });
        }

        // 6. User Sessions (if exists)
        if (Schema::hasTable('user_sessions')) {
            Schema::table('user_sessions', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->foreign('tenant_id')
                    ->references('id')->on('tenants')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We generally don't revert to restricted updates, but we can if needed.
        // For now, simpler down is omitted or just reverses the cascade to strict/default
    }
};
