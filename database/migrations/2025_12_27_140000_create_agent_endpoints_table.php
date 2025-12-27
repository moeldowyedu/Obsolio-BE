<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        Schema::create('agent_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id')->comment('Reference to the agent');
            $table->string('type')->comment('Endpoint type: trigger | callback');
            $table->string('url')->comment('HTTP endpoint URL');
            $table->string('secret')->comment('Secret token for authenticating webhooks');
            $table->boolean('is_active')->default(true)->comment('Whether this endpoint is enabled');
            $table->timestamps();

            // Foreign key to agents table
            $table->foreign('agent_id')
                ->references('id')
                ->on('agents')
                ->onDelete('cascade')
                ->comment('Delete all endpoints when agent is deleted');

            // Indexes for performance
            $table->index('agent_id');
            $table->index('type');
            $table->index(['agent_id', 'type']);
            $table->index('is_active');

            // Ensure each agent has only ONE trigger and ONE callback endpoint
            $table->unique(['agent_id', 'type'], 'unique_agent_endpoint_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_endpoints');
    }
};
