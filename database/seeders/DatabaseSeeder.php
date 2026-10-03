<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Extra;
use App\Models\Area;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\RestaurantSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::firstOrCreate(['email' => 'admin@restaurante.com'], [
            'name' => 'Administrador',
            'password' => Hash::make('password'),
        ]);

        $this->call(UserSeeder::class);
        $this->call(PermissionSeeder::class);

        // Configuración básica del restaurante
        RestaurantSetting::firstOrCreate(['id' => 1], [
            'restaurant_name' => 'Restaurante',
            'description' => 'Experiencia gastronómica de autor',
            'phone' => '744-000-0000',
            'address' => 'Av. Principal 123, Ciudad',
            'schedule' => [
                ['day' => 'Lunes',    'open' => true,  'start' => '13:00', 'end' => '23:00'],
                ['day' => 'Martes',   'open' => true,  'start' => '13:00', 'end' => '23:00'],
                ['day' => 'Miércoles','open' => true,  'start' => '13:00', 'end' => '23:00'],
                ['day' => 'Jueves',   'open' => true,  'start' => '13:00', 'end' => '23:00'],
                ['day' => 'Viernes',  'open' => true,  'start' => '13:00', 'end' => '00:00'],
                ['day' => 'Sábado',   'open' => true,  'start' => '12:00', 'end' => '00:00'],
                ['day' => 'Domingo',  'open' => false, 'start' => '12:00', 'end' => '22:00'],
            ],
        ]);

        /*
        // Seeders de prueba desactivados para producción:
        // - Áreas de prueba
        // - Categorías de prueba
        // - Platillos de prueba
        // - Extras de prueba
        */
    }
}
