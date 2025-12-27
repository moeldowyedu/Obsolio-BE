<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * This migration creates the agent_endpoints table for async execution.
     * - Each agent has TWO endpoints:
     *   1. Trigger endpoint: Where platform sends execution requests
     *   2. Callback endpoint: Where agent sends execution results
     * - Secrets are used to authenticate webhook callbacks
     */
    public function up(): void
    {
        if (!Schema::hasTable('agent_endpoints')) {
            Schema::create('agent_endpoints', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('agent_id');
                $table->string('type', 20)->comment('trigger | callback');
                $table->string('url', 500);
                $table->string('method', 10)->default('POST');
                $table->jsonb('headers')->default('{}');
                $table->string('secret', 255);
                $table->integer('timeout_ms')->default(10000);
                $table->integer('retries')->default(3);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                // Foreign key
                $table->foreign('agent_id')
                    ->references('id')
                    ->on('agents')
                    ->onDelete('cascade');

                // Check constraint for type
                DB::statement('ALTER TABLE agent_endpoints ADD CONSTRAINT check_type CHECK (type IN (\'trigger\', \'callback\'))');

                // Indexes
                $table->index('agent_id');
                $table->index('type');
                $table->index('is_active');
                $table->unique(['agent_id', 'type'], 'unique_agent_endpoint_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_endpoints');
    }
};
