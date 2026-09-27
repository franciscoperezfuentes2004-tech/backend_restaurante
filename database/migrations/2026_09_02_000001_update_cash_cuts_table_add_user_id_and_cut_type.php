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
        Schema::table('cash_cuts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            $table->string('cut_type')->default('repartidor')->after('total_orders');
        });

        // Migrar user_id a partir de delivery_drivers para registros existentes
        DB::statement("
            UPDATE cash_cuts
            SET user_id = delivery_drivers.user_id
            FROM delivery_drivers
            WHERE cash_cuts.driver_id = delivery_drivers.id
        ");

        Schema::table('cash_cuts', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->dropColumn('driver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_cuts', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->after('id')->constrained('delivery_drivers')->cascadeOnDelete();
        });

        DB::statement("
            UPDATE cash_cuts
            SET driver_id = delivery_drivers.id
            FROM delivery_drivers
            WHERE cash_cuts.user_id = delivery_drivers.user_id
        ");

        Schema::table('cash_cuts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'cut_type']);
        });
    }
};
