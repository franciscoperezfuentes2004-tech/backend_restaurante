<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS tables;');
        }

        // 1. Crear tabla sucursales si no existe
        if (!Schema::hasTable('sucursales')) {
            Schema::create('sucursales', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 150);
                $table->string('codigo', 50)->nullable()->unique();
                $table->string('direccion', 255)->nullable();
                $table->string('telefono', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Insertar sucursal inicial por defecto para garantizar integridad referencial
        DB::table('sucursales')->insertOrIgnore([
            'id'         => 1,
            'nombre'     => 'Sucursal Principal',
            'codigo'     => 'SUC-001',
            'direccion'  => 'Matriz Centro',
            'telefono'   => '7441234567',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Agregar sucursal_id a la tabla users (Nullable para Súper Administradores Globales)
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'sucursal_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('sucursal_id')
                    ->nullable()
                    ->after('role')
                    ->constrained('sucursales')
                    ->nullOnDelete();
            });

            // Sincronizar usuarios existentes con branch_id o default 1
            DB::statement("UPDATE users SET sucursal_id = COALESCE(branch_id, 1) WHERE sucursal_id IS NULL AND role != 'super_admin'");
        }

        // 3. Tablas operativas con regla de eliminación CASCADE (catálogos, áreas, inventario general)
        $cascadeTables = [
            'categories',
            'dishes',
            'areas',
            'mesas',
            'reservations',
            'ingredients',
            'suppliers',
            'stock',
            'promotions',
            'reviews',
            'notifications',
            'deliveries',
        ];

        foreach ($cascadeTables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'sucursal_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('sucursal_id')
                        ->default(1)
                        ->constrained('sucursales')
                        ->cascadeOnDelete();
                });
            }
        }

        // 4. Tablas operativas críticas / financieras con regla de eliminación RESTRICT
        // (pedidos, items de pedido, cortes de caja, bitácora de movimientos)
        $restrictTables = [
            'orders',
            'order_items',
            'cash_cuts',
            'stock_movements',
        ];

        foreach ($restrictTables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'sucursal_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('sucursal_id')
                        ->default(1)
                        ->constrained('sucursales')
                        ->restrictOnDelete();
                });
            }
        }

        // 5. Bitácora de auditoría (Nullable con nullOnDelete para preservar históricos de auditoría)
        if (Schema::hasTable('audit_logs') && !Schema::hasColumn('audit_logs', 'sucursal_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->foreignId('sucursal_id')
                    ->nullable()
                    ->default(1)
                    ->constrained('sucursales')
                    ->nullOnDelete();
            });
        }

        // 6. Si existen tablas opcionales de ventas o gastos
        if (Schema::hasTable('sales') && !Schema::hasColumn('sales', 'sucursal_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->foreignId('sucursal_id')
                    ->default(1)
                    ->constrained('sucursales')
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasTable('expenses') && !Schema::hasColumn('expenses', 'sucursal_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('sucursal_id')
                    ->default(1)
                    ->constrained('sucursales')
                    ->restrictOnDelete();
            });
        }

        if (DB::getDriverName() === 'sqlite' && Schema::hasTable('mesas')) {
            DB::statement('DROP VIEW IF EXISTS tables;');
            DB::statement('CREATE VIEW tables AS SELECT * FROM mesas;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $allTables = [
            'expenses',
            'sales',
            'audit_logs',
            'stock_movements',
            'cash_cuts',
            'order_items',
            'orders',
            'deliveries',
            'notifications',
            'reviews',
            'promotions',
            'stock',
            'suppliers',
            'ingredients',
            'reservations',
            'mesas',
            'areas',
            'dishes',
            'categories',
            'users',
        ];

        foreach ($allTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'sucursal_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('sucursal_id');
                });
            }
        }

        if (Schema::hasTable('sucursales')) {
            Schema::dropIfExists('sucursales');
        }
    }
};
