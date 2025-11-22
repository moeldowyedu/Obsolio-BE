<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Change entity_id column from UUID to VARCHAR to support polymorphic relationships
     * that can store both UUID and bigint values (user IDs, agent IDs, etc.)
     */
    public function up(): void
    {
        Schema::table('user_activities', function (Blueprint $table) {
            // Change entity_id from UUID to VARCHAR to support polymorphic IDs
            DB::statement('ALTER TABLE user_activities ALTER COLUMN entity_id TYPE VARCHAR(255)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_activities', function (Blueprint $table) {
            // Revert back to UUID (this may cause data loss for non-UUID values)
            DB::statement('ALTER TABLE user_activities ALTER COLUMN entity_id TYPE UUID USING entity_id::UUID');
        });
    }
};
