<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Check if column exists before adding it (idempotency for manual fixes)
        if (!Schema::hasColumn('tenants', 'type')) {
            Schema::table('tenants', function (Blueprint $table) {
                // Add type column (personal vs organization)
                $table->string('type')->nullable(); // Defaults to nullable, business logic handles default
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'type')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
