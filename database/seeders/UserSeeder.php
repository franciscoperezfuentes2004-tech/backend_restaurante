<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * CAUTION / IMPORTANTE:
 * Este seeder contiene credenciales de prueba para entorno de desarrollo local.
 * ESTE SEEDER NUNCA DEBE EJECUTARSE EN ENTORNOS DE PRODUCCIÓN.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            ['name' => 'Super Administrador', 'email' => 'super@aurum.com', 'role' => 'super_admin'],
            ['name' => 'Administrador','email' => 'admin@aurum.com',      'role' => 'admin'],
            ['name' => 'Gerente',      'email' => 'gerente@aurum.com',    'role' => 'gerente'],
            ['name' => 'Mesero',       'email' => 'mesero@aurum.com',     'role' => 'mesero'],
            ['name' => 'Cocina',       'email' => 'cocina@aurum.com',     'role' => 'cocina'],
            ['name' => 'Repartidor',   'email' => 'repartidor@aurum.com', 'role' => 'repartidor'],
            ['name' => 'Cajero',       'email' => 'cajero@aurum.com',     'role' => 'cajero'],
        ];

        foreach ($usuarios as $usuario) {
            $isDefaultSuper = ($usuario['email'] === 'super@aurum.com');
            $password = $isDefaultSuper ? 'SuperAdmin123' : 'password123';

            User::updateOrCreate(
                ['email' => $usuario['email']],
                [
                    'name'                      => $usuario['name'],
                    'password'                  => Hash::make($password),
                    'role'                      => $usuario['role'],
                    'using_default_credentials' => $isDefaultSuper,
                ]
            );
        }
    }
}
