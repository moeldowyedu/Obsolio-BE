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
        Schema::table('tenants', function (Blueprint $table) {
            // Track whether tenant setup is completed
            $table->boolean('setup_completed')->default(false)->after('data');

            // Store plan information
            $table->string('plan_id')->nullable()->after('setup_completed');
            $table->string('billing_cycle')->nullable()->after('plan_id'); // monthly, yearly

            // Organization-specific fields
            $table->string('organization_name')->nullable()->after('billing_cycle');
            $table->string('slug')->nullable()->unique()->after('organization_name');

            // Setup metadata
            $table->timestamp('setup_completed_at')->nullable()->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'setup_completed',
                'plan_id',
                'billing_cycle',
                'organization_name',
                'slug',
                'setup_completed_at',
            ]);
        });
    }
};
