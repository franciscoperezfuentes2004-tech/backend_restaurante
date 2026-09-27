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
        Schema::table('delivery_drivers', function (Blueprint $table) {
            $table->enum('vehicle_type', ['motorcycle', 'bicycle', 'car'])->default('motorcycle')->after('phone');
            $table->string('license_plate', 20)->nullable()->after('vehicle_type');
            $table->string('avatar')->nullable()->after('license_plate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_drivers', function (Blueprint $table) {
            $table->dropColumn(['vehicle_type', 'license_plate', 'avatar']);
        });
    }
};
