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
        if (Schema::hasTable('reviews') && !Schema::hasColumn('reviews', 'estado')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->string('estado', 50)->default('aprobado')->nullable()->after('is_approved');
            });

            // Sincronizar registros existentes
            DB::table('reviews')->where('is_approved', true)->update(['estado' => 'aprobado']);
            DB::table('reviews')->where('is_approved', false)->update(['estado' => 'pendiente']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'estado')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropColumn('estado');
            });
        }
    }
};
