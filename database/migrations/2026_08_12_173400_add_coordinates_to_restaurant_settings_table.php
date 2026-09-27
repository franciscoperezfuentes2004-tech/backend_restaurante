<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            if (!Schema::hasColumn('restaurant_settings', 'delivery_radius_km')) {
                $table->decimal('delivery_radius_km', 8, 2)->nullable()->default(5);
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'delivery_radius_km']);
        });
    }
};
