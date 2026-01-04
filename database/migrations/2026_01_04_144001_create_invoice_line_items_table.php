<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();

            // Relationship
            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->onDelete('cascade');

            // Item Details
            $table->string('item_type', 50)
                ->comment('base_plan, agent_addon, usage_overage, discount, tax');

            $table->text('description')
                ->comment('Human-readable description');

            $table->integer('quantity')
                ->default(1)
                ->comment('Quantity (e.g., number of executions)');

            $table->decimal('unit_price', 10, 4)
                ->nullable()
                ->comment('Price per unit');

            $table->decimal('total_price', 10, 2)
                ->comment('Total for this line item');

            // Optional Agent Reference
            $table->foreignUuid('agent_id')
                ->nullable()
                ->constrained('agents')
                ->onDelete('set null')
                ->comment('For agent-related charges');

            // Metadata
            $table->jsonb('metadata')
                ->nullable()
                ->comment('Additional data (e.g., execution count, period)');

            // Timestamp (no updated_at - immutable records)
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('invoice_id');
            $table->index('item_type');
            $table->index('agent_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoice_line_items');
    }
};
