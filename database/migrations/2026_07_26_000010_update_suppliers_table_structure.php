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
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'name')) {
                $table->renameColumn('name', 'company_name');
            }
            if (!Schema::hasColumn('suppliers', 'contact_name')) {
                $table->string('contact_name')->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('suppliers', 'specialty')) {
                $table->string('specialty')->default('General')->after('contact_name');
            }
            if (!Schema::hasColumn('suppliers', 'delivery_days')) {
                $table->json('delivery_days')->nullable()->after('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'company_name')) {
                $table->renameColumn('company_name', 'name');
            }
            if (Schema::hasColumn('suppliers', 'contact_name')) {
                $table->dropColumn('contact_name');
            }
            if (Schema::hasColumn('suppliers', 'specialty')) {
                $table->dropColumn('specialty');
            }
            if (Schema::hasColumn('suppliers', 'delivery_days')) {
                $table->dropColumn('delivery_days');
            }
        });
    }
};
