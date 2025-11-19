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
        // Update webhooks table
        Schema::table('webhooks', function (Blueprint $table) {
            $table->jsonb('headers')->nullable()->after('secret');
            $table->integer('total_calls')->default(0)->after('is_active');
            $table->integer('failed_calls')->default(0)->after('total_calls');

            // Rename created_by_user_id to user_id for consistency
            $table->renameColumn('created_by_user_id', 'user_id');
        });

        // Update api_keys table
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('key_prefix', 20)->after('name');
            $table->jsonb('scopes')->default('[]')->after('key_prefix');

            // Rename created_by_user_id to user_id for consistency
            $table->renameColumn('created_by_user_id', 'user_id');

            // Rename permissions to match scopes
            $table->dropColumn('permissions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropColumn(['headers', 'total_calls', 'failed_calls']);
            $table->renameColumn('user_id', 'created_by_user_id');
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn(['key_prefix', 'scopes']);
            $table->renameColumn('user_id', 'created_by_user_id');
            $table->jsonb('permissions')->default('{}');
        });
    }
};
