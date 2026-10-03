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
            if (!Schema::hasColumn('restaurant_settings', 'active_notification_platform')) {
                $table->string('active_notification_platform')->default('none')->after('banco_titular');
            }
            if (!Schema::hasColumn('restaurant_settings', 'discord_webhook_url')) {
                $table->string('discord_webhook_url', 500)->nullable()->after('active_notification_platform');
            }
            if (!Schema::hasColumn('restaurant_settings', 'telegram_bot_token')) {
                $table->string('telegram_bot_token', 255)->nullable()->after('discord_webhook_url');
            }
            if (!Schema::hasColumn('restaurant_settings', 'telegram_chat_id')) {
                $table->string('telegram_chat_id', 255)->nullable()->after('telegram_bot_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('restaurant_settings', 'active_notification_platform')) {
                $columns[] = 'active_notification_platform';
            }
            if (Schema::hasColumn('restaurant_settings', 'discord_webhook_url')) {
                $columns[] = 'discord_webhook_url';
            }
            if (Schema::hasColumn('restaurant_settings', 'telegram_bot_token')) {
                $columns[] = 'telegram_bot_token';
            }
            if (Schema::hasColumn('restaurant_settings', 'telegram_chat_id')) {
                $columns[] = 'telegram_chat_id';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
