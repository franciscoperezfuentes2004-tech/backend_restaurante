<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('telefono', 20);
            $table->string('correo');
            $table->smallInteger('rating');
            $table->text('comentario');
            $table->json('fotos')->nullable();
            $table->boolean('is_approved')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Restricción estricta en PostgreSQL: rating solo aceptará números del 1 al 5
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reviews ADD CONSTRAINT check_reviews_rating_range CHECK (rating >= 1 AND rating <= 5)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
