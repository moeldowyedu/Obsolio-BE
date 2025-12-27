<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * This migration creates the agent_runs table for tracking async executions.
     * - Each run represents one asynchronous execution of an agent
     * - Status flows: pending → running → completed/failed
     * - Input and output are stored as JSONB for flexibility
     */
    public function up(): void
    {
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Unique run identifier');
            $table->uuid('agent_id')->comment('Reference to the agent being executed');
            $table->string('status')->comment('Execution status: pending | running | completed | failed');
            $table->jsonb('input')->comment('Input parameters sent to the agent');
            $table->jsonb('output')->nullable()->comment('Result returned by the agent (null until completed)');
            $table->text('error')->nullable()->comment('Error message if execution failed');
            $table->timestamps();

            // Foreign key to agents table
            $table->foreign('agent_id')
                ->references('id')
                ->on('agents')
                ->onDelete('cascade')
                ->comment('Delete all runs when agent is deleted');

            // Indexes for performance
            $table->index('agent_id');
            $table->index('status');
            $table->index(['agent_id', 'status']);
            $table->index('created_at');
            $table->index(['agent_id', 'created_at']); // For agent execution history
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_runs');
    }
};
