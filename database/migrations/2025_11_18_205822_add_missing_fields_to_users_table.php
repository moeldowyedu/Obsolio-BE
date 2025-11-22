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
        Schema::table('users', function (Blueprint $table) {
            // Keep ID as bigint (do not change to UUID)
            // $table->uuid('id')->primary()->change();

            // Add multi-tenancy support
            $table->string('tenant_id')->after('id')->index();

            // Add user profile fields
            $table->string('avatar_url', 500)->nullable()->after('email_verified_at');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('avatar_url');
            $table->timestamp('last_login_at')->nullable()->after('status');

            // Add foreign key for tenant
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn([
                'tenant_id',
                'avatar_url',
                'status',
                'last_login_at',
            ]);

            // ID remains as bigint - no change needed
            // $table->id()->change();
        });
    }
};
