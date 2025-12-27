<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * This migration modifies the existing agents table to support asynchronous execution:
     * - Adds columns: runtime_type (nullable), execution_timeout_ms
     * - Removes columns: category, total_installs, rating, review_count, is_marketplace
     *
     * Note: The runtime_type column is kept nullable here. The NOT NULL constraint
     * will be added in migration 2025_12_27_130000_finalize_agents_table_changes.php
     * AFTER the data migration populates the values.
     */
    public function up(): void
    {
        // STEP 1: Add new columns (runtime_type is nullable for now)
        Schema::table('agents', function (Blueprint $table) {
            // Check if columns don't already exist
            if (!Schema::hasColumn('agents', 'runtime_type')) {
                $table->string('runtime_type')
                    ->nullable() // Will be made NOT NULL after data migration
                    ->after('created_by_user_id')
                    ->comment('Runtime environment: n8n | custom');
            }

            if (!Schema::hasColumn('agents', 'execution_timeout_ms')) {
                $table->integer('execution_timeout_ms')
                    ->default(30000)
                    ->after('created_by_user_id')
                    ->comment('Maximum execution time in milliseconds (default: 30 seconds)');
            }
        });

        // STEP 2: Remove old columns
        Schema::table('agents', function (Blueprint $table) {
            // Drop indexes first if they exist
            if (Schema::hasColumn('agents', 'category')) {
                $table->dropIndex(['agents_category_index']);
                $table->dropIndex(['agents_category_is_active_index']);
            }
            if (Schema::hasColumn('agents', 'is_marketplace')) {
                $table->dropIndex(['agents_is_marketplace_index']);
            }

            // Remove columns that are no longer needed
            $columns_to_drop = [];
            if (Schema::hasColumn('agents', 'category')) $columns_to_drop[] = 'category';
            if (Schema::hasColumn('agents', 'total_installs')) $columns_to_drop[] = 'total_installs';
            if (Schema::hasColumn('agents', 'rating')) $columns_to_drop[] = 'rating';
            if (Schema::hasColumn('agents', 'review_count')) $columns_to_drop[] = 'review_count';
            if (Schema::hasColumn('agents', 'is_marketplace')) $columns_to_drop[] = 'is_marketplace';

            if (!empty($columns_to_drop)) {
                $table->dropColumn($columns_to_drop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Remove the new columns
            $table->dropColumn(['runtime_type', 'execution_timeout_ms']);

            // Restore the old columns
            $table->string('category', 100)->after('slug');
            $table->integer('total_installs')->default(0)->after('version');
            $table->decimal('rating', 3, 2)->default(0)->after('total_installs');
            $table->integer('review_count')->default(0)->after('rating');
            $table->boolean('is_marketplace')->default(true)->after('annual_price');

            // Restore indexes
            $table->index('category');
            $table->index('is_marketplace');
            $table->index(['category', 'is_active']);
        });
    }
};
