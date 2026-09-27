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
        // Índices para la tabla de órdenes (Filtros de panel y reportes)
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasIndex('orders', ['status'])) {
                $table->index('status');
            }
            if (!Schema::hasIndex('orders', ['created_at'])) {
                $table->index('created_at');
            }
        });

        // Índices para la tabla de platillos (Carga del menú en la Landing Page)
        Schema::table('dishes', function (Blueprint $table) {
            if (!Schema::hasIndex('dishes', ['is_available'])) {
                $table->index('is_available');
            }
        });

        // Índices para la tabla de entregas (Asignación de repartidores)
        Schema::table('deliveries', function (Blueprint $table) {
            if (!Schema::hasIndex('deliveries', ['driver_id'])) {
                $table->index('driver_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasIndex('orders', ['status'])) {
                $table->dropIndex(['status']);
            }
            if (Schema::hasIndex('orders', ['created_at'])) {
                $table->dropIndex(['created_at']);
            }
        });

        Schema::table('dishes', function (Blueprint $table) {
            if (Schema::hasIndex('dishes', ['is_available'])) {
                $table->dropIndex(['is_available']);
            }
        });

        Schema::table('deliveries', function (Blueprint $table) {
            if (Schema::hasIndex('deliveries', ['driver_id'])) {
                $table->dropIndex(['driver_id']);
            }
        });
    }
};
