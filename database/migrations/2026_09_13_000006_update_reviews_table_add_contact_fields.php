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
        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'codigo_referencia')) {
                $table->dropColumn('codigo_referencia');
            }
            if (Schema::hasColumn('reviews', 'platillo_consumido')) {
                $table->dropColumn('platillo_consumido');
            }
            if (!Schema::hasColumn('reviews', 'telefono')) {
                $table->string('telefono', 20)->after('nombre');
            }
            if (!Schema::hasColumn('reviews', 'correo')) {
                $table->string('correo')->after('telefono');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'correo')) {
                $table->dropColumn('correo');
            }
            if (Schema::hasColumn('reviews', 'telefono')) {
                $table->dropColumn('telefono');
            }
            if (!Schema::hasColumn('reviews', 'codigo_referencia')) {
                $table->string('codigo_referencia', 50)->nullable();
            }
            if (!Schema::hasColumn('reviews', 'platillo_consumido')) {
                $table->string('platillo_consumido', 150)->nullable();
            }
        });
    }
};
