<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the restrictive check constraint
        DB::statement("ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_guard_tenant_check");

        // Add a more flexible constraint that allows 'web' guard for system admins
        DB::statement("
            ALTER TABLE roles
            ADD CONSTRAINT roles_guard_tenant_check
            CHECK (
                (guard_name = 'console' AND tenant_id IS NULL) OR
                (guard_name = 'tenant' AND tenant_id IS NOT NULL) OR
                (guard_name = 'web' AND tenant_id IS NULL) OR
                (guard_name = 'api' AND tenant_id IS NULL)
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore original constraint
        DB::statement("ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_guard_tenant_check");

        DB::statement("
            ALTER TABLE roles
            ADD CONSTRAINT roles_guard_tenant_check
            CHECK (
                (guard_name = 'console' AND tenant_id IS NULL) OR
                (guard_name = 'tenant' AND tenant_id IS NOT NULL)
            )
        ");
    }
};
