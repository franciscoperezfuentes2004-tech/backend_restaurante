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
        // 1. Agregar status a la tabla mesas
        if (!Schema::hasColumn('mesas', 'status')) {
            Schema::table('mesas', function (Blueprint $table) {
                $table->string('status')->default('libre')->after('is_active');
            });
        }

        // 2. Agregar table_id y waiter_id a la tabla orders
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'table_id')) {
                $table->foreignId('table_id')->nullable()->after('customer_email')->constrained('mesas')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'waiter_id')) {
                $table->foreignId('waiter_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
        });

        // 3. Sincronizar la vista `tables` para reflejar la columna `status`
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS tables;');
            DB::statement('CREATE VIEW tables AS SELECT * FROM mesas;');
        } else {
            // En PostgreSQL recrear la vista con CREATE OR REPLACE
            DB::statement('CREATE OR REPLACE VIEW tables AS SELECT * FROM mesas;');
        }

        // 4. Crear índice único parcial para garantizar integridad referencial estricta:
        // Una mesa no puede tener dos órdenes con estado abierto/pendiente simultáneamente.
        try {
            DB::statement("
                CREATE UNIQUE INDEX IF NOT EXISTS unique_active_order_per_table 
                ON orders (table_id) 
                WHERE table_id IS NOT NULL 
                  AND status IN ('pending', 'preparing', 'ready', 'open', 'abierto', 'en_preparacion');
            ");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Could not create partial unique index on orders: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('DROP INDEX IF EXISTS unique_active_order_per_table;');
        } catch (\Throwable $e) {}

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'table_id')) {
                $table->dropForeign(['table_id']);
                $table->dropColumn('table_id');
            }
            if (Schema::hasColumn('orders', 'waiter_id')) {
                $table->dropForeign(['waiter_id']);
                $table->dropColumn('waiter_id');
            }
        });

        if (Schema::hasColumn('mesas', 'status')) {
            Schema::table('mesas', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS tables;');
            DB::statement('CREATE VIEW tables AS SELECT * FROM mesas;');
        } else {
            DB::statement('CREATE OR REPLACE VIEW tables AS SELECT * FROM mesas;');
        }
    }
};
