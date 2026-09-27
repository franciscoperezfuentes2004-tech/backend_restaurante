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
        Schema::table('testimonials', function (Blueprint $table) {
            if (!Schema::hasColumn('testimonials', 'is_approved')) {
                $table->boolean('is_approved')->default(false)->after('status')->index();
            }
        });

        // Sincronizar registros existentes que ya estén aprobados o respondidos
        DB::table('testimonials')
            ->whereIn('status', ['aprobada', 'published', 'respondida'])
            ->update(['is_approved' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            if (Schema::hasColumn('testimonials', 'is_approved')) {
                $table->dropColumn('is_approved');
            }
        });
    }
};
