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
        Schema::create('billing_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('monthly, semi_annual, annual');
            $table->string('name', 50);
            $table->integer('months')->comment('1, 6, or 12');
            $table->decimal('discount_percentage', 5, 2)->default(0)->comment('0, 10, or 20');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_cycles');
    }
};
