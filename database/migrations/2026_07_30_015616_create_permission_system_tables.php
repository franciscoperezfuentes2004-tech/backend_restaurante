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
        // 1. permissions
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('modulo');
            $table->string('clave')->unique();
            $table->timestamps();
        });

        // 2. role_permissions
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['role', 'permission_id']);
        });

        // 3. user_permissions
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
    }
};
