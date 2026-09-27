<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->text('allergens')->nullable()->after('image_url');
            $table->text('ingredients')->nullable()->after('allergens');
            $table->boolean('allow_extras')->default(false)->after('ingredients');
            $table->boolean('allow_observations')->default(false)->after('allow_extras');
        });
    }

    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->dropColumn(['allergens', 'ingredients', 'allow_extras', 'allow_observations']);
        });
    }
};
