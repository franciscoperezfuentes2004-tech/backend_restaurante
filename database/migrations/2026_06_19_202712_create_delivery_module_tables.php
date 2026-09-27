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
        Schema::create('delivery_drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('shipping_cost', 10, 2);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('delivery_drivers')->onDelete('set null');
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->onDelete('set null');
            $table->enum('status', ['pending', 'in_transit', 'delivered', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('delivery_drivers');
    }
};
