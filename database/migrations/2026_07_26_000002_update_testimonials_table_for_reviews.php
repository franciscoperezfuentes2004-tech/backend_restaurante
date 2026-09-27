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
        Schema::table('testimonials', function (Blueprint $table) {
            if (!Schema::hasColumn('testimonials', 'origen')) {
                $table->string('origen', 30)->default('consumo')->after('rating');
            }
            if (!Schema::hasColumn('testimonials', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('origen');
            }
            if (!Schema::hasColumn('testimonials', 'response')) {
                $table->text('response')->nullable()->after('comment');
            }
            // Modify status column type to string to support all review statuses: pendiente, respondida, reportada, aprobada, oculta
            $table->string('status', 30)->default('pendiente')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            if (Schema::hasColumn('testimonials', 'origen')) {
                $table->dropColumn('origen');
            }
            if (Schema::hasColumn('testimonials', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
            if (Schema::hasColumn('testimonials', 'response')) {
                $table->dropColumn('response');
            }
        });
    }
};
