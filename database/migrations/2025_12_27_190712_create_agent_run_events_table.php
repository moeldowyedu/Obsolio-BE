<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('agent_run_events')) {
            Schema::create('agent_run_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('run_id');
                $table->string('event', 20);
                $table->jsonb('payload')->nullable();
                $table->timestamp('created_at');

                // Foreign key
                $table->foreign('run_id')
                    ->references('id')
                    ->on('agent_runs')
                    ->onDelete('cascade');

                // Indexes
                $table->index('run_id');
                $table->index('event');
                $table->index('created_at');
            });

            // Add check constraint after table creation
            DB::statement("ALTER TABLE agent_run_events ADD CONSTRAINT check_event CHECK (event IN ('accepted', 'running', 'progress', 'completed', 'failed', 'usage'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_run_events');
    }
};
