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
        Schema::table('cash_cuts', function (Blueprint $table) {
            $table->decimal('received_cash', 10, 2)->nullable()->after('expected_cash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_cuts', function (Blueprint $table) {
            $table->dropColumn('received_cash');
        });
    }
};
