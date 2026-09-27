<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Permitir nulos en columnas heredadas para que no bloqueen inserciones directas con nuevas columnas
            $table->string('customer_name')->nullable()->change();
            $table->string('customer_phone')->nullable()->change();
            $table->date('reservation_date')->nullable()->change();
            $table->time('reservation_time')->nullable()->change();
            $table->integer('guests_count')->nullable()->change();

            // Agregar columnas con los tipos de datos exactos solicitados
            if (!Schema::hasColumn('reservations', 'nombre')) {
                $table->string('nombre', 100)->nullable();
            }
            if (!Schema::hasColumn('reservations', 'telefono')) {
                $table->string('telefono', 15)->nullable();
            }
            if (!Schema::hasColumn('reservations', 'email')) {
                $table->string('email', 150)->nullable();
            }
            if (!Schema::hasColumn('reservations', 'fecha')) {
                $table->date('fecha')->nullable();
            }
            if (!Schema::hasColumn('reservations', 'hora')) {
                $table->time('hora')->nullable();
            }
            if (!Schema::hasColumn('reservations', 'personas')) {
                $table->integer('personas')->nullable();
            }
            if (!Schema::hasColumn('reservations', 'zona_preferida')) {
                $table->string('zona_preferida', 50)->nullable();
            }
            if (!Schema::hasColumn('reservations', 'ocasion_especial')) {
                $table->string('ocasion_especial', 50)->nullable();
            }
            if (!Schema::hasColumn('reservations', 'nota_especial')) {
                $table->text('nota_especial')->nullable();
            }
            if (!Schema::hasColumn('reservations', 'estado')) {
                $table->string('estado', 20)->default('pendiente');
            }
        });

        // Sincronizar registros existentes
        DB::statement("
            UPDATE reservations
            SET nombre = COALESCE(nombre, customer_name),
                telefono = COALESCE(telefono, customer_phone),
                email = COALESCE(email, customer_email),
                fecha = COALESCE(fecha, reservation_date),
                hora = COALESCE(hora, reservation_time),
                personas = COALESCE(personas, guests_count),
                nota_especial = COALESCE(nota_especial, special_requests),
                estado = COALESCE(estado, CASE WHEN status = 'pending' THEN 'pendiente' WHEN status = 'confirmed' THEN 'confirmada' WHEN status = 'cancelled' THEN 'cancelada' ELSE status END)
            WHERE nombre IS NULL OR telefono IS NULL OR fecha IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $colsToDrop = [
                'nombre', 'telefono', 'email', 'fecha', 'hora',
                'personas', 'zona_preferida', 'ocasion_especial',
                'nota_especial', 'estado'
            ];
            foreach ($colsToDrop as $col) {
                if (Schema::hasColumn('reservations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};