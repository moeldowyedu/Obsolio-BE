<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Make phone required (non-nullable) and remove remember_token
     * since we're using JWT authentication instead of session-based auth
     */
    public function up(): void
    {
        // First, update any existing NULL phone values with empty string
        DB::table('users')->whereNull('phone')->update(['phone' => '']);

        Schema::table('users', function (Blueprint $table) {
            // Make phone NOT NULL
            $table->string('phone', 20)->nullable(false)->change();

            // Remove remember_token since we're using JWT
            $table->dropColumn('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Make phone nullable again
            $table->string('phone', 20)->nullable()->change();

            // Re-add remember_token
            $table->rememberToken();
        });
    }
};
