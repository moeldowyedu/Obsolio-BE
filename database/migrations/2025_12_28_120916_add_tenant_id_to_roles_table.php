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
        // Step 1: Drop existing unique constraint on (name, guard_name)
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);
        });

        // Step 2: Add tenant_id column
        Schema::table('roles', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('guard_name');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->index('tenant_id');
        });

        // Step 3: Update existing roles to console scope FIRST (before constraints)
        DB::statement("
            UPDATE roles
            SET guard_name = 'console'
            WHERE guard_name NOT IN ('console', 'tenant')
        ");

        // Step 4: Add new composite unique constraints using partial indexes
        // For tenant roles: unique(tenant_id, name, guard_name) WHERE tenant_id IS NOT NULL
        DB::statement("
            CREATE UNIQUE INDEX roles_tenant_name_guard_unique
            ON roles (tenant_id, name, guard_name)
            WHERE tenant_id IS NOT NULL
        ");

        // For console roles: unique(name, guard_name) WHERE tenant_id IS NULL
        DB::statement("
            CREATE UNIQUE INDEX roles_console_name_guard_unique
            ON roles (name, guard_name)
            WHERE tenant_id IS NULL
        ");

        // Step 5: Add check constraint (AFTER data is fixed)
        DB::statement("
            ALTER TABLE roles
            ADD CONSTRAINT roles_guard_tenant_check
            CHECK (
                (guard_name = 'console' AND tenant_id IS NULL) OR
                (guard_name = 'tenant' AND tenant_id IS NOT NULL)
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop check constraint
        DB::statement("ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_guard_tenant_check");

        // Drop partial unique indexes
        DB::statement("DROP INDEX IF EXISTS roles_tenant_name_guard_unique");
        DB::statement("DROP INDEX IF EXISTS roles_console_name_guard_unique");

        // Drop tenant_id column
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        // Restore original unique constraint
        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['name', 'guard_name']);
        });
    }
};
