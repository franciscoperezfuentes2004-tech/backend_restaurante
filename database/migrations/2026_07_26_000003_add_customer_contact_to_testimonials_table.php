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
            if (!Schema::hasColumn('testimonials', 'customer_email')) {
                $table->string('customer_email')->nullable()->after('customer_name');
            }
            if (!Schema::hasColumn('testimonials', 'customer_phone')) {
                $table->string('customer_phone', 30)->nullable()->after('customer_email');
            }
            if (!Schema::hasColumn('testimonials', 'replied_by_user_id')) {
                $table->foreignId('replied_by_user_id')->nullable()->after('replied_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            if (Schema::hasColumn('testimonials', 'customer_email')) {
                $table->dropColumn('customer_email');
            }
            if (Schema::hasColumn('testimonials', 'customer_phone')) {
                $table->dropColumn('customer_phone');
            }
            if (Schema::hasColumn('testimonials', 'replied_by_user_id')) {
                $table->dropForeign(['replied_by_user_id']);
                $table->dropColumn('replied_by_user_id');
            }
        });
    }
};
