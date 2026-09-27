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
        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                if (!Schema::hasColumn('reviews', 'respuesta_admin')) {
                    $table->text('respuesta_admin')->nullable()->after('comentario');
                }
                if (!Schema::hasColumn('reviews', 'fecha_respuesta')) {
                    $table->timestamp('fecha_respuesta')->nullable()->after('respuesta_admin');
                }
            });
        }

        if (Schema::hasTable('testimonials')) {
            Schema::table('testimonials', function (Blueprint $table) {
                if (!Schema::hasColumn('testimonials', 'respuesta_admin')) {
                    $table->text('respuesta_admin')->nullable()->after('response');
                }
                if (!Schema::hasColumn('testimonials', 'fecha_respuesta')) {
                    $table->timestamp('fecha_respuesta')->nullable()->after('respuesta_admin');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                if (Schema::hasColumn('reviews', 'fecha_respuesta')) {
                    $table->dropColumn('fecha_respuesta');
                }
                if (Schema::hasColumn('reviews', 'respuesta_admin')) {
                    $table->dropColumn('respuesta_admin');
                }
            });
        }

        if (Schema::hasTable('testimonials')) {
            Schema::table('testimonials', function (Blueprint $table) {
                if (Schema::hasColumn('testimonials', 'fecha_respuesta')) {
                    $table->dropColumn('fecha_respuesta');
                }
                if (Schema::hasColumn('testimonials', 'respuesta_admin')) {
                    $table->dropColumn('respuesta_admin');
                }
            });
        }
    }
};
