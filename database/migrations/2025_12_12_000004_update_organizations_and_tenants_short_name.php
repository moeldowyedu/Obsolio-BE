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
        // 1. Add short_name to organizations table
        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'short_name')) {
                $table->string('short_name')->nullable()->after('name');
            }
        });

        // 2. Remove unique constraint from tenants table short_name
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'short_name')) {
                // Drop unique index.
                // Syntax: $table->dropUnique(['column_name']); or $table->dropUnique('table_column_unique');
                // Laravel usually names it table_column_unique
                $table->dropUnique(['short_name']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop short_name from organizations
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('short_name');
        });

        // 2. Add unique constraint back to tenants
        Schema::table('tenants', function (Blueprint $table) {
            $table->unique('short_name');
        });
    }
};
