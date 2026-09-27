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
            if (!Schema::hasColumn('restaurant_settings', 'location_text')) {
                $table->string('location_text')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'hero_slogan')) {
                $table->text('hero_slogan')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'hero_image_url')) {
                $table->string('hero_image_url')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'menu_subtitle')) {
                $table->string('menu_subtitle')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'menu_title')) {
                $table->string('menu_title')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'delivery_title')) {
                $table->string('delivery_title')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'delivery_description')) {
                $table->text('delivery_description')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'contact_phone')) {
                $table->string('contact_phone')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'contact_email')) {
                $table->string('contact_email')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'facebook_url')) {
                $table->string('facebook_url')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'instagram_url')) {
                $table->string('instagram_url')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'tiktok_url')) {
                $table->string('tiktok_url')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'cta_menu_text')) {
                $table->string('cta_menu_text')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'cta_reservation_text')) {
                $table->string('cta_reservation_text')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'contact_whatsapp')) {
                $table->string('contact_whatsapp')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'contact_whatsapp_url')) {
                $table->string('contact_whatsapp_url')->nullable();
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
                'location_text',
                'hero_slogan',
                'hero_image_url',
                'menu_subtitle',
                'menu_title',
                'delivery_title',
                'delivery_description',
                'contact_phone',
                'contact_email',
                'facebook_url',
                'instagram_url',
                'tiktok_url',
                'cta_menu_text',
                'cta_reservation_text',
                'contact_whatsapp',
                'contact_whatsapp_url',
            ]);
        });
    }
};
