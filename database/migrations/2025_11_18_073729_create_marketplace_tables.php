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
        // Marketplace Listings table
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id');
            $table->string('seller_tenant_id');
            $table->string('title');
            $table->text('description');
            $table->string('category', 100)->nullable();
            $table->string('industry', 100)->nullable();
            $table->enum('price_type', ['free', 'one-time', 'subscription'])->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('thumbnail_url', 500)->nullable();
            $table->jsonb('screenshots')->nullable();
            $table->jsonb('tags')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected', 'unlisted'])->default('pending');
            $table->integer('views_count')->default(0);
            $table->integer('purchases_count')->default(0);
            $table->decimal('rating_average', 3, 2)->nullable();
            $table->integer('reviews_count')->default(0);
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
            $table->foreign('seller_tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        // Marketplace Purchases table
        Schema::create('marketplace_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('listing_id');
            $table->string('buyer_tenant_id');
            $table->unsignedBigInteger('purchased_by_user_id')->nullable();
            $table->decimal('price_paid', 10, 2);
            $table->string('currency', 3);
            $table->timestamp('purchased_at')->useCurrent();

            $table->foreign('listing_id')->references('id')->on('marketplace_listings')->cascadeOnDelete();
            $table->foreign('buyer_tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('purchased_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_purchases');
        Schema::dropIfExists('marketplace_listings');
    }
};
