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
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('guard_name')->default('web');
                $table->timestamps();
            });

            $roles = [
                'super_admin',
                'admin',
                'gerente',
                'cajero',
                'mesero',
                'cocina',
                'repartidor',
                'manager',
                'waiter',
                'kitchen',
                'driver',
                'superadmin',
                'administrador',
            ];

            foreach ($roles as $role) {
                DB::table('roles')->insertOrIgnore([
                    'name'       => $role,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
