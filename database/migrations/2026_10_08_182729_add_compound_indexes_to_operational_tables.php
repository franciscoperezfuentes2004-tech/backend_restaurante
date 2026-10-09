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
        // 1. Tabla orders (Pedidos)
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasIndex('orders', ['sucursal_id', 'status'])) {
                    $table->index(['sucursal_id', 'status']);
                }
                if (!Schema::hasIndex('orders', ['sucursal_id', 'created_at'])) {
                    $table->index(['sucursal_id', 'created_at']);
                }
            });
        }

        // 2. Tabla users (Usuarios/Empleados)
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasIndex('users', ['sucursal_id', 'role'])) {
                    $table->index(['sucursal_id', 'role']);
                }
            });
        }

        // 3. Tabla dishes (Equivalente principal de menú/platillos/productos)
        if (Schema::hasTable('dishes')) {
            Schema::table('dishes', function (Blueprint $table) {
                if (!Schema::hasIndex('dishes', ['sucursal_id', 'category_id'])) {
                    $table->index(['sucursal_id', 'category_id']);
                }
                if (Schema::hasColumn('dishes', 'is_active') && !Schema::hasIndex('dishes', ['sucursal_id', 'is_active'])) {
                    $table->index(['sucursal_id', 'is_active']);
                }
            });
        }

        // Tabla products (en caso de existir como tabla independiente)
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'sucursal_id') && Schema::hasColumn('products', 'category_id')) {
                    if (!Schema::hasIndex('products', ['sucursal_id', 'category_id'])) {
                        $table->index(['sucursal_id', 'category_id']);
                    }
                }
                if (Schema::hasColumn('products', 'sucursal_id') && Schema::hasColumn('products', 'is_active')) {
                    if (!Schema::hasIndex('products', ['sucursal_id', 'is_active'])) {
                        $table->index(['sucursal_id', 'is_active']);
                    }
                }
            });
        }

        // 4. Tabla sales (Ventas)
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (Schema::hasColumn('sales', 'sucursal_id') && Schema::hasColumn('sales', 'created_at')) {
                    if (!Schema::hasIndex('sales', ['sucursal_id', 'created_at'])) {
                        $table->index(['sucursal_id', 'created_at']);
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Tabla orders (Pedidos)
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasIndex('orders', ['sucursal_id', 'status'])) {
                    $table->dropIndex(['sucursal_id', 'status']);
                }
                if (Schema::hasIndex('orders', ['sucursal_id', 'created_at'])) {
                    $table->dropIndex(['sucursal_id', 'created_at']);
                }
            });
        }

        // 2. Tabla users (Usuarios/Empleados)
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasIndex('users', ['sucursal_id', 'role'])) {
                    $table->dropIndex(['sucursal_id', 'role']);
                }
            });
        }

        // 3. Tabla dishes (Menú/Platillos/Productos)
        if (Schema::hasTable('dishes')) {
            Schema::table('dishes', function (Blueprint $table) {
                if (Schema::hasIndex('dishes', ['sucursal_id', 'category_id'])) {
                    $table->dropIndex(['sucursal_id', 'category_id']);
                }
                if (Schema::hasIndex('dishes', ['sucursal_id', 'is_active'])) {
                    $table->dropIndex(['sucursal_id', 'is_active']);
                }
            });
        }

        // Tabla products (en caso de existir)
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasIndex('products', ['sucursal_id', 'category_id'])) {
                    $table->dropIndex(['sucursal_id', 'category_id']);
                }
                if (Schema::hasIndex('products', ['sucursal_id', 'is_active'])) {
                    $table->dropIndex(['sucursal_id', 'is_active']);
                }
            });
        }

        // 4. Tabla sales (Ventas)
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (Schema::hasIndex('sales', ['sucursal_id', 'created_at'])) {
                    $table->dropIndex(['sucursal_id', 'created_at']);
                }
            });
        }
    }
};
