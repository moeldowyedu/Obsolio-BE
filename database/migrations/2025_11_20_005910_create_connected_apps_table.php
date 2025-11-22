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
        Schema::create('connected_apps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->uuid('organization_id');
            $table->string('app_name');
            $table->string('app_type'); // 'oauth', 'api_key', 'webhook', 'custom'
            $table->string('provider')->nullable(); // 'github', 'gitlab', 'slack', 'custom', etc.
            $table->text('description')->nullable();
            $table->string('client_id')->nullable();
            $table->string('client_secret')->nullable();
            $table->json('credentials')->nullable(); // Encrypted OAuth tokens, refresh tokens, etc.
            $table->json('scopes')->nullable();
            $table->json('settings')->nullable(); // App-specific settings
            $table->string('status')->default('active'); // 'active', 'inactive', 'expired', 'revoked'
            $table->string('callback_url')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->integer('total_requests')->default(0);
            $table->integer('failed_requests')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            $table->index(['tenant_id', 'organization_id']);
            $table->index(['user_id', 'status']);
            $table->index('app_type');
            $table->index('provider');
        });

        // Create a table to log connected app activities
        Schema::create('connected_app_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('connected_app_id');
            $table->string('action'); // 'request', 'sync', 'error', 'auth', etc.
            $table->string('status'); // 'success', 'failed', 'pending'
            $table->text('message')->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->integer('response_code')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('connected_app_id')->references('id')->on('connected_apps')->cascadeOnDelete();

            $table->index(['connected_app_id', 'created_at']);
            $table->index('status');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connected_app_logs');
        Schema::dropIfExists('connected_apps');
    }
};
