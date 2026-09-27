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
        // 1. Update or create areas table
        if (!Schema::hasTable('areas')) {
            Schema::create('areas', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->integer('capacidad_personas')->default(0);
                $table->integer('numero_mesas')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('areas', function (Blueprint $table) {
                if (!Schema::hasColumn('areas', 'nombre')) {
                    $table->string('nombre')->nullable()->after('id');
                }
                if (!Schema::hasColumn('areas', 'capacidad_personas')) {
                    $table->integer('capacidad_personas')->default(0)->after('nombre');
                }
                if (!Schema::hasColumn('areas', 'numero_mesas')) {
                    $table->integer('numero_mesas')->default(0)->after('capacidad_personas');
                }
                if (!Schema::hasColumn('areas', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('numero_mesas');
                }
                if (!Schema::hasColumn('areas', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // 2. Create mesas table
        if (!Schema::hasTable('mesas')) {
            Schema::create('mesas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
                $table->integer('numero_mesa');
                $table->integer('capacidad')->default(4); // personas por mesa
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mesas');
    }
};
