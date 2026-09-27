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
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (!Schema::hasColumn('notifications', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('notifications', 'deleted_at')) {
                    $table->softDeletes()->after('read_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (Schema::hasColumn('notifications', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
                if (Schema::hasColumn('notifications', 'user_id')) {
                    $table->dropConstrainedForeignId('user_id');
                }
            });
        }
    }
};