<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_published');
            $table->index(['type', 'is_default'], 'subscription_plans_type_is_default_index');
        });

        // Mark current default plans
        DB::table('subscription_plans')
            ->where('type', 'organization')
            ->where('tier', 'team')
            ->update(['is_default' => true]);

        DB::table('subscription_plans')
            ->where('type', 'personal')
            ->where('tier', 'free')
            ->update(['is_default' => true]);

        // Add unique constraint: only one default per type
        // PostgreSQL partial unique index
        DB::statement('
            CREATE UNIQUE INDEX subscription_plans_type_is_default_unique 
            ON subscription_plans (type) 
            WHERE is_default = true
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop unique constraint
        DB::statement('DROP INDEX IF EXISTS subscription_plans_type_is_default_unique');

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropIndex('subscription_plans_type_is_default_index');
            $table->dropColumn('is_default');
        });
    }
};
