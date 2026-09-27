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
        Schema::create('configuracion_general', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_comercial')->default('Aurum Restaurant');
            $table->string('logotipo')->nullable();
            $table->string('fondo_sistema')->default('#1C1917');
            $table->enum('modo_fondo', ['claro', 'oscuro'])->default('oscuro');
            $table->string('color_primario')->default('#7c3aed');
            $table->string('color_apoyo')->nullable();
            $table->boolean('color_apoyo_activo')->default(false);
            $table->boolean('delivery_activo')->default(true);
            $table->decimal('costo_envio_fijo', 10, 2)->default(0);
            $table->decimal('envio_gratis_desde', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_general');
    }
};
