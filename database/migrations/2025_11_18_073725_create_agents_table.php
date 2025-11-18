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
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('created_by_user_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['custom', 'marketplace', 'template'])->default('custom');
            $table->jsonb('engines_used');
            $table->jsonb('config');
            $table->jsonb('input_schema')->nullable();
            $table->jsonb('output_schema')->nullable();
            $table->jsonb('rubric_config')->nullable();
            $table->enum('status', ['draft', 'active', 'inactive', 'archived'])->default('draft');
            $table->boolean('is_published')->default(false);
            $table->uuid('marketplace_listing_id')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('tenant_id');
            $table->index('status');
        });

        // Create GIN index for engines_used array (PostgreSQL specific)
        DB::statement('CREATE INDEX agents_engines_used_idx ON agents USING GIN (engines_used)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
