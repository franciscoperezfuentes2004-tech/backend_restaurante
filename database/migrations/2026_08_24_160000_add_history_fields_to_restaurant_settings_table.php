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
            if (!Schema::hasColumn('restaurant_settings', 'history_title')) {
                $table->string('history_title')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'history_description')) {
                $table->text('history_description')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'history_year')) {
                $table->integer('history_year')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'history_image')) {
                $table->string('history_image')->nullable();
            }
            if (!Schema::hasColumn('restaurant_settings', 'history_features')) {
                $table->json('history_features')->nullable();
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
                'history_title',
                'history_description',
                'history_year',
                'history_image',
                'history_features',
            ]);
        });
    }
};
