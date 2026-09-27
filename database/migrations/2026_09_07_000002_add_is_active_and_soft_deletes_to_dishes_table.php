<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            if (!Schema::hasColumn('dishes', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_available');
            }
            if (!Schema::hasColumn('dishes', 'is_sold_out')) {
                $table->boolean('is_sold_out')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('dishes', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Sincronizar is_active inicial a partir de is_available
        if (Schema::hasColumn('dishes', 'is_available') && Schema::hasColumn('dishes', 'is_active')) {
            DB::statement('UPDATE dishes SET is_active = is_available WHERE is_available IS NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            if (Schema::hasColumn('dishes', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('dishes', 'is_sold_out')) {
                $table->dropColumn('is_sold_out');
            }
            if (Schema::hasColumn('dishes', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
