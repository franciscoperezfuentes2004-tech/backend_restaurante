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
        if (!Schema::hasTable('ingredient_product')) {
            Schema::create('ingredient_product', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('dishes')->onDelete('cascade');
                $table->foreignId('ingredient_id')->constrained('ingredients')->onDelete('cascade');
                $table->decimal('cantidad_requerida', 10, 4);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_product');
    }
};