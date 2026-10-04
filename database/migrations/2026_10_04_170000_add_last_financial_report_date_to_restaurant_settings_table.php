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
            if (!Schema::hasColumn('restaurant_settings', 'last_financial_report_date')) {
                $table->date('last_financial_report_date')->nullable()->default(null)->after('telegram_settings');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            if (Schema::hasColumn('restaurant_settings', 'last_financial_report_date')) {
                $table->dropColumn('last_financial_report_date');
            }
        });
    }
};
