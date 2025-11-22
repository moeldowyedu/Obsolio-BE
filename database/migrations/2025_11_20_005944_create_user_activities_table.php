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
        Schema::create('user_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->uuid('organization_id')->nullable();
            $table->string('activity_type'); // 'login', 'logout', 'api_call', 'data_access', 'data_modification', 'settings_change', 'app_connection', 'export', etc.
            $table->string('action'); // 'create', 'read', 'update', 'delete', 'download', 'upload', 'share', etc.
            $table->string('entity_type')->nullable(); // 'user', 'project', 'agent', 'workflow', etc.
            $table->uuid('entity_id')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional context data
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_type')->nullable(); // 'desktop', 'mobile', 'tablet', 'api'
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('location')->nullable(); // City, Country
            $table->string('status')->default('success'); // 'success', 'failed', 'warning'
            $table->text('error_message')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->boolean('is_sensitive')->default(false); // Flag for sensitive operations
            $table->boolean('requires_audit')->default(false); // Flag for operations requiring audit trail
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            $table->index(['tenant_id', 'user_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index('activity_type');
            $table->index('action');
            $table->index(['entity_type', 'entity_id']);
            $table->index('status');
            $table->index('is_sensitive');
            $table->index('requires_audit');
        });

        // Create a table for session tracking
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('session_id')->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['tenant_id', 'user_id']);
            $table->index('is_active');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('user_activities');
    }
};
