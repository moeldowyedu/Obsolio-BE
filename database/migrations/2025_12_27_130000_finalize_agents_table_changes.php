<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * This migration finalizes the agents table changes by making runtime_type NOT NULL.
     * This runs AFTER the data migration has populated all runtime_type values with defaults.
     */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Make runtime_type NOT NULL now that all agents have a value
            $table->string('runtime_type')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Make runtime_type nullable again
            $table->string('runtime_type')->nullable()->change();
        });
    }
};
