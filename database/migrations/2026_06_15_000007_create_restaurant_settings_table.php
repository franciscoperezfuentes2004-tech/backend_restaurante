<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_settings', function (Blueprint $table) {
            $table->id();
            $table->string('restaurant_name')->default('Aurum');
            $table->string('logo_url')->nullable();
            $table->text('description')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('whatsapp')->nullable();
            $table->integer('delivery_radius_km')->default(5);
            $table->json('schedule')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_settings');
    }
};
