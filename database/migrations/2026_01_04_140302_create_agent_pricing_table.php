<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('agent_id')->constrained('agents')->onDelete('cascade');
            $table->foreignId('tier_id')->nullable()->constrained('agent_tiers')->onDelete('set null');

            // Pricing
            $table->decimal('monthly_price', 10, 2)->comment('Add-on monthly price');
            $table->decimal('price_per_task', 10, 4)->nullable()->comment('Cost per execution');
            $table->integer('included_tasks_per_month')->default(0)->comment('Free tasks per month');

            // Validity
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->default(DB::raw('CURRENT_DATE'));
            $table->date('effective_to')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('agent_id');
            $table->index('tier_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_pricing');
    }
};
