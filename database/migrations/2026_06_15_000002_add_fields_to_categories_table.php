<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('description');
            $table->time('time_start')->nullable()->after('image_url');
            $table->time('time_end')->nullable()->after('time_start');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'time_start', 'time_end']);
        });
    }
};
