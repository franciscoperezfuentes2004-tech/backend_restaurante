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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->default(1)->after('role');
            $table->string('branch_name', 100)->nullable()->default('Sucursal Centro')->after('branch_id');
        });

        Schema::table('user_sessions', function (Blueprint $table) {
            $table->string('last_ip', 45)->nullable()->after('ip_address');
            $table->timestamp('last_seen_at')->nullable()->after('last_activity');
            $table->timestamp('ended_at')->nullable()->after('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['branch_id', 'branch_name']);
        });

        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropColumn(['last_ip', 'last_seen_at', 'ended_at']);
        });
    }
};
