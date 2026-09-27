<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Category;
use App\Models\RestaurantSetting;
use App\Models\ConfiguracionGeneral;

class ProductionDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Usuario Super Admin Genérico
        User::updateOrCreate(
            ['email' => 'admin@restaurante.com'],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('AdminSeguro2026!'), // Contraseña genérica
                'role'     => 'super_admin',
            ]
        );

        // 2. Categorías Base del Restaurante
        $categorias = [
            ['name' => 'Entradas', 'slug' => 'entradas', 'is_active' => true],
            ['name' => 'Platos Fuertes', 'slug' => 'platos-fuertes', 'is_active' => true],
            ['name' => 'Bebidas', 'slug' => 'bebidas', 'is_active' => true],
            ['name' => 'Postres', 'slug' => 'postres', 'is_active' => true],
        ];

        foreach ($categorias as $categoria) {
            Category::updateOrCreate(
                ['slug' => $categoria['slug']],
                $categoria
            );
        }

        // 3. Configuración General Mínima Genérica
        $settings = [
            ['key' => 'restaurant_name', 'value' => 'Mi Restaurante'],
            ['key' => 'currency', 'value' => 'MXN'],
            ['key' => 'tax_rate_percentage', 'value' => '16'],
            ['key' => 'timezone', 'value' => 'America/Mexico_City'],
        ];

        if (Schema::hasTable('settings')) {
            foreach ($settings as $setting) {
                DB::table('settings')->updateOrInsert(['key' => $setting['key']], $setting);
            }
        }

        // Adaptación a tablas de configuración existentes en el sistema (aurum_db)
        if (Schema::hasTable('restaurant_settings')) {
            RestaurantSetting::updateOrCreate(
                ['id' => 1],
                [
                    'restaurant_name' => 'Mi Restaurante',
                    'brand_color'     => '#C5A880',
                ]
            );
        }

        if (Schema::hasTable('configuracion_general')) {
            ConfiguracionGeneral::updateOrCreate(
                ['id' => 1],
                [
                    'nombre_comercial' => 'Mi Restaurante',
                    'color_primario'   => '#C5A880',
                ]
            );
        }
    }
}
