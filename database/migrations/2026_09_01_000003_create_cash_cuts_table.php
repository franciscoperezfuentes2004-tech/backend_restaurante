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
        Schema::create('cash_cuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('delivery_drivers')->onDelete('cascade');
            $table->decimal('cash_declared', 10, 2)->default(0.00);
            $table->decimal('expected_cash', 10, 2)->default(0.00);
            $table->integer('total_orders')->default(0);
            $table->string('status')->default('pendiente');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_cuts');
    }
};
