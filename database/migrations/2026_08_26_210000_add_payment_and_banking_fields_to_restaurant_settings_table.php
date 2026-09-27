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
        Schema::table('restaurant_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurant_settings', 'acepta_efectivo')) {
                $table->boolean('acepta_efectivo')->default(true);
            }
            if (!Schema::hasColumn('restaurant_settings', 'acepta_tarjeta')) {
                $table->boolean('acepta_tarjeta')->default(true);
            }
            if (!Schema::hasColumn('restaurant_settings', 'acepta_transferencia')) {
                $table->boolean('acepta_transferencia')->default(false);
            }
            if (!Schema::hasColumn('restaurant_settings', 'banco_nombre')) {
                $table->string('banco_nombre')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'banco_clabe')) {
                $table->string('banco_clabe')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'banco_titular')) {
                $table->string('banco_titular')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'acepta_efectivo',
                'acepta_tarjeta',
                'acepta_transferencia',
                'banco_nombre',
                'banco_clabe',
                'banco_titular',
            ]);
        });
    }
};
