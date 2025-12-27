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
        if (!Schema::hasTable('agent_runs')) {
            Schema::create('agent_runs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('agent_id');
                $table->string('state', 20)->default('pending');
                $table->jsonb('input');
                $table->jsonb('output')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                // Foreign key
                $table->foreign('agent_id')
                    ->references('id')
                    ->on('agents')
                    ->onDelete('cascade');

                // Check constraint for state
                DB::statement("ALTER TABLE agent_runs ADD CONSTRAINT check_state CHECK (state IN ('pending', 'accepted', 'running', 'completed', 'failed', 'cancelled', 'timeout'))");

                // Indexes
                $table->index('agent_id');
                $table->index('state');
                $table->index('started_at');
                $table->index(['agent_id', 'state']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_runs');
    }
};
