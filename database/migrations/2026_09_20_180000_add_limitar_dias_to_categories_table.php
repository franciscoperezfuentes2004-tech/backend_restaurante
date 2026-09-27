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
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (!Schema::hasColumn('categories', 'limitar_dias')) {
                    $table->boolean('limitar_dias')->default(false)->after('active');
                }
                if (!Schema::hasColumn('categories', 'dias_disponibilidad')) {
                    $table->json('dias_disponibilidad')->nullable()->after('limitar_dias');
                }
            });
        }

        if (Schema::hasTable('dishes')) {
            Schema::table('dishes', function (Blueprint $table) {
                if (!Schema::hasColumn('dishes', 'limitar_dias')) {
                    $table->boolean('limitar_dias')->default(false)->after('is_available');
                }
                if (!Schema::hasColumn('dishes', 'dias_disponibilidad')) {
                    $table->json('dias_disponibilidad')->nullable()->after('limitar_dias');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (Schema::hasColumn('categories', 'dias_disponibilidad')) {
                    $table->dropColumn('dias_disponibilidad');
                }
                if (Schema::hasColumn('categories', 'limitar_dias')) {
                    $table->dropColumn('limitar_dias');
                }
            });
        }

        if (Schema::hasTable('dishes')) {
            Schema::table('dishes', function (Blueprint $table) {
                if (Schema::hasColumn('dishes', 'dias_disponibilidad')) {
                    $table->dropColumn('dias_disponibilidad');
                }
                if (Schema::hasColumn('dishes', 'limitar_dias')) {
                    $table->dropColumn('limitar_dias');
                }
            });
        }
    }
};
