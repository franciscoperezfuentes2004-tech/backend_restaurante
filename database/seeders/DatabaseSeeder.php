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
        User::firstOrCreate(['email' => 'admin@aurum.com'], [
            'name' => 'Administrador',
            'password' => Hash::make('password'),
        ]);

        $this->call(UserSeeder::class);

        // Configuración del restaurante
        RestaurantSetting::firstOrCreate(['id' => 1], [
            'restaurant_name' => 'Aurum',
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

        // Áreas
        $areas = [
            ['name' => 'Interior',  'capacity' => 40, 'active' => true],
            ['name' => 'Terraza',   'capacity' => 20, 'active' => true],
            ['name' => 'Barra',     'capacity' => 8,  'active' => true],
        ];
        foreach ($areas as $area) {
            Area::firstOrCreate(['name' => $area['name']], $area);
        }

        // Categorías
        $categories = [
            ['name' => 'Entradas Celestiales',  'time_start' => '12:00', 'time_end' => '23:00', 'image_url' => 'https://picsum.photos/seed/entradas/400/300'],
            ['name' => 'Carnes & Brasa Premium', 'time_start' => '13:00', 'time_end' => '23:00', 'image_url' => 'https://picsum.photos/seed/carnes/400/300'],
            ['name' => 'Pastas Artesanales',    'time_start' => '13:00', 'time_end' => '22:00', 'image_url' => 'https://picsum.photos/seed/pastas/400/300'],
            ['name' => 'Postres de Autor',      'time_start' => '12:00', 'time_end' => '23:00', 'image_url' => 'https://picsum.photos/seed/postres/400/300', 'active' => false],
        ];
        foreach ($categories as $cat) {
            Category::firstOrCreate(['name' => $cat['name']], array_merge($cat, [
                'slug' => Str::slug($cat['name']),
                'active' => $cat['active'] ?? true,
            ]));
        }

        // Platillos
        $entradas = Category::where('name', 'Entradas Celestiales')->first();
        $carnes   = Category::where('name', 'Carnes & Brasa Premium')->first();
        $pastas   = Category::where('name', 'Pastas Artesanales')->first();

        $dishes = [
            ['category_id' => $entradas->id, 'name' => 'Carpaccio de Wagyu Premium',        'price' => 24.50, 'image_url' => 'https://picsum.photos/seed/wagyu/400/300', 'description' => 'Finas láminas de Wagyu con trufa y parmesano'],
            ['category_id' => $entradas->id, 'name' => 'Flores de Calabacín en Tempura',    'price' => 16.00, 'image_url' => 'https://picsum.photos/seed/flores/400/300', 'description' => 'Flores crujientes rellenas de ricotta'],
            ['category_id' => $carnes->id,   'name' => 'Ribeye Black Angus a la Leña',      'price' => 48.00, 'image_url' => 'https://picsum.photos/seed/ribeye/400/300', 'description' => 'Corte premium de 400g a las brasas'],
            ['category_id' => $carnes->id,   'name' => 'Lomo Fino de Cordero en Vino Tinto','price' => 42.50, 'image_url' => 'https://picsum.photos/seed/cordero/400/300', 'description' => 'Lomo de cordero con reducción de vino tinto', 'is_available' => false],
            ['category_id' => $pastas->id,   'name' => 'Tagliatelle de Trufa Negra',        'price' => 32.00, 'image_url' => 'https://picsum.photos/seed/pasta/400/300', 'description' => 'Pasta artesanal con trufa negra y mantequilla'],
        ];
        foreach ($dishes as $dish) {
            Dish::firstOrCreate(['name' => $dish['name']], array_merge($dish, [
                'slug' => Str::slug($dish['name']),
                'is_available' => $dish['is_available'] ?? true,
            ]));
        }

        // Extras
        $extras = [
            ['name' => 'Queso extra',          'price' => 2.00],
            ['name' => 'Sin cebolla',           'price' => 0.00],
            ['name' => 'Salsa adicional',       'price' => 1.50],
            ['name' => 'Porción extra de pan',  'price' => 1.00],
        ];
        foreach ($extras as $extra) {
            Extra::firstOrCreate(['name' => $extra['name']], $extra);
        }
    }
}
