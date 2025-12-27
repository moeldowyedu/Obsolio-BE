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
        // 1. Ensure 'organizations' table exists
        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->string('name');
                $table->string('industry', 100)->nullable();
                $table->string('company_size', 50)->nullable();
                $table->string('country', 100)->nullable();
                $table->string('timezone', 50)->default('UTC');
                $table->string('logo_url', 500)->nullable();
                $table->text('description')->nullable();
                $table->jsonb('settings')->default('{}');
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->index('tenant_id');
            });
        }

        // 2. Ensure 'tenant_agents' table exists
        if (!Schema::hasTable('tenant_agents')) {
            Schema::create('tenant_agents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->uuid('agent_id');
                $table->enum('status', ['active', 'inactive', 'expired', 'suspended'])->default('active');
                $table->timestamp('purchased_at')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->integer('usage_count')->default(0);
                $table->jsonb('configuration')->default('{}');
                $table->jsonb('metadata')->default('{}');
                $table->timestamps();

                // Foreign Keys
                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');

                $table->foreign('agent_id')
                    ->references('id')
                    ->on('agents')
                    ->onDelete('cascade');

                // Unique Constraint
                $table->unique(['tenant_id', 'agent_id']);

                // Indexes
                $table->index('tenant_id');
                $table->index('agent_id');
                $table->index('status');
                $table->index(['tenant_id', 'status']);
            });
        }

        // 3. Ensure 'workflows' table exists
        if (!Schema::hasTable('workflows')) {
            Schema::create('workflows', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->uuid('organization_id')->nullable();
                $table->uuid('department_id')->nullable();
                $table->uuid('project_id')->nullable();
                $table->jsonb('nodes');
                $table->jsonb('edges');
                $table->jsonb('config')->nullable();
                $table->enum('status', ['draft', 'active', 'inactive'])->default('draft');
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            });
        }

        // 4. Ensure 'workflow_executions' table exists
        if (!Schema::hasTable('workflow_executions')) {
            Schema::create('workflow_executions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('workflow_id');
                $table->string('tenant_id');
                $table->unsignedBigInteger('triggered_by_user_id')->nullable();
                $table->enum('status', ['running', 'completed', 'failed', 'cancelled'])->default('running');
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('completed_at')->nullable();
                $table->jsonb('execution_data')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('workflow_id')->references('id')->on('workflows')->cascadeOnDelete();
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('triggered_by_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // 5. Ensure 'short_name' exists in 'tenants' table
        if (Schema::hasTable('tenants') && !Schema::hasColumn('tenants', 'short_name')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('short_name')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We do not drop tables here because this is a safety check migration.
    }
};
