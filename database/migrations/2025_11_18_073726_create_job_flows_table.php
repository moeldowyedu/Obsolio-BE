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
        Schema::create('job_flows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('agent_id');
            $table->string('job_title');
            $table->text('job_description')->nullable();
            $table->uuid('organization_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->uuid('department_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->uuid('reporting_manager_id')->nullable();
            $table->enum('employment_type', ['full-time', 'part-time', 'on-demand'])->default('full-time');
            $table->enum('schedule_type', ['one-time', 'hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'half-yearly', 'yearly', 'custom']);
            $table->jsonb('schedule_config');
            $table->jsonb('input_source');
            $table->jsonb('output_destination');
            $table->enum('hitl_mode', ['fully-ai', 'hitl', 'standby', 'in-charge', 'hybrid'])->default('fully-ai');
            $table->uuid('hitl_supervisor_id')->nullable();
            $table->jsonb('hitl_rules')->nullable();
            $table->enum('status', ['active', 'inactive', 'paused'])->default('active');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->integer('total_runs')->default(0);
            $table->integer('successful_runs')->default(0);
            $table->integer('failed_runs')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('reporting_manager_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('hitl_supervisor_id')->references('id')->on('users')->nullOnDelete();

            $table->index('tenant_id');
            $table->index('agent_id');
            $table->index('department_id');
            $table->index(['next_run_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_flows');
    }
};
