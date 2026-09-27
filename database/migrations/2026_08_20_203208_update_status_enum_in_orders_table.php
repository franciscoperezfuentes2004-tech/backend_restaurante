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
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status::text IN ('pending', 'preparing', 'ready', 'completed', 'cancelled', 'delivered'));");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status::text IN ('pending', 'preparing', 'completed', 'cancelled'));");
        }
    }
};
