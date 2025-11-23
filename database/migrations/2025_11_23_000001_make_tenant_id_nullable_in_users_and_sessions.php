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
        // Make tenant_id nullable in users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->string('tenant_id')->nullable()->change();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Make tenant_id nullable in user_sessions table
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->string('tenant_id')->nullable()->change();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Make tenant_id nullable in user_activities table
        Schema::table('user_activities', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->string('tenant_id')->nullable()->change();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert tenant_id to non-nullable in users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->string('tenant_id')->nullable(false)->change();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Revert tenant_id to non-nullable in user_sessions table
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->string('tenant_id')->nullable(false)->change();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Revert tenant_id to non-nullable in user_activities table
        Schema::table('user_activities', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->string('tenant_id')->nullable(false)->change();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }
};
