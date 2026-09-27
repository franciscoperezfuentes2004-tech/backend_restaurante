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
            $table->json('platillos_seccion')->nullable();
            $table->json('delivery_seccion')->nullable();
            $table->json('banner_descuento')->nullable();
            $table->json('reservaciones_seccion')->nullable();
            $table->json('historia_config')->nullable();
            $table->json('servicios_config')->nullable();
            $table->json('contacto_seccion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'platillos_seccion',
                'delivery_seccion',
                'banner_descuento',
                'reservaciones_seccion',
                'historia_config',
                'servicios_config',
                'contacto_seccion',
            ]);
        });
    }
};
