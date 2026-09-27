<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('type', 20); // 2x1, 3x2, 3x1, fixed, combo, personalizado
            $table->string('scheme', 50)->nullable(); // esquema personalizado
            $table->string('benefit')->nullable(); // texto del beneficio computado
            $table->json('products')->nullable(); // array de IDs de platillos
            $table->json('days')->nullable(); // array de días: Lun, Mar, Mié...
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->string('time_start', 5)->nullable(); // HH:MM
            $table->string('time_end', 5)->nullable();   // HH:MM
            $table->boolean('active')->default(true);
            $table->boolean('show_on_landing')->default(true);
            $table->string('aplica_en', 20)->default('pedidos'); // pedidos, reservaciones, ambos
            $table->string('mensaje_banner')->nullable();
            $table->date('fecha_inicio')->nullable(); // fecha especial reservaciones
            $table->date('fecha_fin')->nullable();     // fecha especial reservaciones
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
