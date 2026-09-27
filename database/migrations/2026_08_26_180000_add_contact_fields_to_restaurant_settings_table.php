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
            if (!Schema::hasColumn('restaurant_settings', 'email')) {
                $table->string('email')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'correo')) {
                $table->string('correo')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'correo_contacto')) {
                $table->string('correo_contacto')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'telefono')) {
                $table->string('telefono')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'telefono_publico')) {
                $table->string('telefono_publico')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'tiktok')) {
                $table->string('tiktok')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'numero_whatsapp')) {
                $table->string('numero_whatsapp')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'correo',
                'correo_contacto',
                'telefono',
                'telefono_publico',
                'tiktok',
                'numero_whatsapp',
            ]);
        });
    }
};
