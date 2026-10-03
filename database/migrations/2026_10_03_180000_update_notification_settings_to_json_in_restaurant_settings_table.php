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
        Schema::table('restaurant_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurant_settings', 'discord_settings')) {
                $table->json('discord_settings')->nullable()->after('active_notification_platform');
            }
            if (!Schema::hasColumn('restaurant_settings', 'telegram_settings')) {
                $table->json('telegram_settings')->nullable()->after('discord_settings');
            }
        });

        // Migrar valores previos si existían columnas simples
        try {
            $settings = DB::table('restaurant_settings')->first();
            if ($settings) {
                $discord = [];
                if (!empty($settings->discord_webhook_url ?? null)) {
                    $discord['orders_webhook_url'] = $settings->discord_webhook_url;
                    $discord['reservations_webhook_url'] = $settings->discord_webhook_url;
                }

                $telegram = [];
                if (!empty($settings->telegram_bot_token ?? null)) {
                    $telegram['bot_token'] = $settings->telegram_bot_token;
                }
                if (!empty($settings->telegram_chat_id ?? null)) {
                    $telegram['orders_chat_id'] = $settings->telegram_chat_id;
                    $telegram['reservations_chat_id'] = $settings->telegram_chat_id;
                }

                DB::table('restaurant_settings')->where('id', $settings->id)->update([
                    'discord_settings'  => !empty($discord) ? json_encode($discord) : null,
                    'telegram_settings' => !empty($telegram) ? json_encode($telegram) : null,
                ]);
            }
        } catch (\Throwable $e) {
            // Ignorar si las columnas no existían
        }

        // Eliminar columnas legacy de texto plano
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('restaurant_settings', 'discord_webhook_url')) {
                $drop[] = 'discord_webhook_url';
            }
            if (Schema::hasColumn('restaurant_settings', 'telegram_bot_token')) {
                $drop[] = 'telegram_bot_token';
            }
            if (Schema::hasColumn('restaurant_settings', 'telegram_chat_id')) {
                $drop[] = 'telegram_chat_id';
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurant_settings', 'discord_webhook_url')) {
                $table->string('discord_webhook_url', 500)->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'telegram_bot_token')) {
                $table->string('telegram_bot_token', 255)->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'telegram_chat_id')) {
                $table->string('telegram_chat_id', 255)->nullable();
            }
            if (Schema::hasColumn('restaurant_settings', 'discord_settings')) {
                $table->dropColumn('discord_settings');
            }
            if (Schema::hasColumn('restaurant_settings', 'telegram_settings')) {
                $table->dropColumn('telegram_settings');
            }
        });
    }
};
