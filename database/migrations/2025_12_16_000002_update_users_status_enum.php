<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['pending_verification', 'active', 'inactive', 'suspended'])
                ->default('pending_verification')
                ->after('avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'suspended'])
                ->default('active')
                ->after('avatar_url');
        });
    }
};
