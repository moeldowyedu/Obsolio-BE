<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('agent_id');
            $table->uuid('job_flow_id')->nullable();
            $table->uuid('workflow_execution_id')->nullable();
            $table->string('triggered_by', 50);
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->jsonb('input_data');
            $table->jsonb('output_data')->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('rubric_scores')->nullable();
            $table->string('hitl_status', 20)->nullable();
            $table->text('logs')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
            $table->foreign('job_flow_id')->references('id')->on('job_flows')->nullOnDelete();
            $table->foreign('workflow_execution_id')->references('id')->on('workflow_executions')->nullOnDelete();
            $table->foreign('triggered_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('tenant_id');
            $table->index('agent_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_executions');
    }
};
