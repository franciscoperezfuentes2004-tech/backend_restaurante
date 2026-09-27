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
            if (!Schema::hasColumn('reviews', 'codigo_referencia')) {
                $table->string('codigo_referencia', 50)->nullable()->after('fotos');
            }
            if (!Schema::hasColumn('reviews', 'platillo_consumido')) {
                $table->string('platillo_consumido', 150)->nullable()->after('codigo_referencia');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'platillo_consumido')) {
                $table->dropColumn('platillo_consumido');
            }
            if (Schema::hasColumn('reviews', 'codigo_referencia')) {
                $table->dropColumn('codigo_referencia');
            }
        });
    }
};
