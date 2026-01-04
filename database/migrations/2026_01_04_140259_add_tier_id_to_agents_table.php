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
        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('tier_id')
                ->nullable()
                ->after('category')
                ->constrained('agent_tiers')
                ->onDelete('set null');

            $table->index('tier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['tier_id']);
            $table->dropIndex(['tier_id']);
            $table->dropColumn('tier_id');
        });
    }
};
