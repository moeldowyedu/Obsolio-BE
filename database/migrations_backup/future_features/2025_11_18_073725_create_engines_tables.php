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
        // Engines table
        Schema::create('engines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable();
            $table->jsonb('capabilities')->default('[]');
            $table->jsonb('input_types')->default('[]');
            $table->string('color', 7)->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('default_rubric')->nullable();
            $table->timestamps();
        });

        // Engine Rubrics table
        Schema::create('engine_rubrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('engine_id');
            $table->string('name');
            $table->jsonb('criteria');
            $table->jsonb('weights');
            $table->decimal('threshold', 5, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('engine_id')->references('id')->on('engines')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engine_rubrics');
        Schema::dropIfExists('engines');
    }
};
