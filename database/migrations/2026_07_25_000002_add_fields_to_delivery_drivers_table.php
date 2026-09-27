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
        Schema::table('delivery_drivers', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_drivers', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            }
            if (!Schema::hasColumn('delivery_drivers', 'email')) {
                $table->string('email')->nullable();
            }
            if (!Schema::hasColumn('delivery_drivers', 'active')) {
                $table->boolean('active')->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_drivers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'email', 'active']);
        });
    }
};
