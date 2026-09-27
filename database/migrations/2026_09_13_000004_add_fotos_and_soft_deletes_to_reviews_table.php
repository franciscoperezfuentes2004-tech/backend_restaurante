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
        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'fotos')) {
                $table->json('fotos')->nullable()->after('comentario');
            }
            if (!Schema::hasColumn('reviews', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'fotos')) {
                $table->dropColumn('fotos');
            }
            if (Schema::hasColumn('reviews', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
