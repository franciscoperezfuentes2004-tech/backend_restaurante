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
            if (Schema::hasColumn('restaurant_settings', 'coverage_polygon')) {
                $table->dropColumn('coverage_polygon');
            }
            if (Schema::hasColumn('restaurant_settings', 'coverage_neighborhoods')) {
                $table->dropColumn('coverage_neighborhoods');
            }
            $table->integer('delivery_radius_meters')->default(3000);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->dropColumn('delivery_radius_meters');
            $table->json('coverage_polygon')->nullable();
        });
    }
};
