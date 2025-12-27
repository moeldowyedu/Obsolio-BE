<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * This migration modifies the existing agents table to support asynchronous execution:
     * - Adds columns: runtime_type, execution_timeout_ms (FIRST)
     * - Then removes columns: category, total_installs, rating, review_count, is_marketplace
     *
     * Note: Data migration (2025_12_27_000000_migrate_agent_categories_data) should run
     * AFTER agent_categories and agent_category_map tables are created but BEFORE this migration.
     */
    public function up(): void
    {
        // STEP 1: Add new columns FIRST (so data migration can use them)
        Schema::table('agents', function (Blueprint $table) {
            // Check if columns don't already exist
            if (!Schema::hasColumn('agents', 'runtime_type')) {
                $table->string('runtime_type')
                    ->nullable() // Temporarily nullable for data migration
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

        // STEP 2: Now remove old columns
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

        // STEP 3: Make runtime_type NOT NULL now that data migration has run
        Schema::table('agents', function (Blueprint $table) {
            $table->string('runtime_type')->nullable(false)->change();
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
