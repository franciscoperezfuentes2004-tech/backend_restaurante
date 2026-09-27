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
        if (Schema::hasTable('reviews') && !Schema::hasColumn('reviews', 'folio')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->string('folio', 30)->nullable()->index()->after('id');
            });
        }

        if (Schema::hasTable('testimonials') && !Schema::hasColumn('testimonials', 'folio')) {
            Schema::table('testimonials', function (Blueprint $table) {
                $table->string('folio', 30)->nullable()->index()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'folio')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropColumn('folio');
            });
        }

        if (Schema::hasTable('testimonials') && Schema::hasColumn('testimonials', 'folio')) {
            Schema::table('testimonials', function (Blueprint $table) {
                $table->dropColumn('folio');
            });
        }
    }
};
