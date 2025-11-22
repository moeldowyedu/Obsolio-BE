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
        Schema::create('user_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('tenant_id');
            $table->uuid('organization_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->uuid('department_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->jsonb('access_scope')->default('{}');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_assignments');
    }
};
