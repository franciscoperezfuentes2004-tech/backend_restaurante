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
        if (!Schema::hasTable('dish_ingredient')) {
            Schema::create('dish_ingredient', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dish_id')->constrained('dishes')->onDelete('cascade');
                $table->foreignId('ingredient_id')->constrained('ingredients')->onDelete('cascade');
                $table->decimal('cantidad_requerida', 10, 4)->default(1.0000);
                $table->timestamps();
            });
        }

        Schema::table('ingredients', function (Blueprint $table) {
            if (!Schema::hasColumn('ingredients', 'stock_actual')) {
                $table->decimal('stock_actual', 10, 4)->default(0.0000);
            }
            if (!Schema::hasColumn('ingredients', 'stock_minimo')) {
                $table->decimal('stock_minimo', 10, 4)->default(0.0000);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dish_ingredient');

        Schema::table('ingredients', function (Blueprint $table) {
            if (Schema::hasColumn('ingredients', 'stock_actual')) {
                $table->dropColumn('stock_actual');
            }
            if (Schema::hasColumn('ingredients', 'stock_minimo')) {
                $table->dropColumn('stock_minimo');
            }
        });
    }
};
