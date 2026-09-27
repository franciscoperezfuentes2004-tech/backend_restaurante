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
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->json('cover_images')->nullable();
            $table->string('brand_color')->nullable()->default('#7c3aed');
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->decimal('free_delivery_over', 8, 2)->default(0);
            $table->string('hero_title')->nullable();
            $table->text('hero_description')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'cover_images', 'brand_color', 'delivery_fee',
                'free_delivery_over', 'hero_title', 'hero_description'
            ]);
        });
    }
};
