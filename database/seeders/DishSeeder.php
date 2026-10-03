<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Dish;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DishSeeder extends Seeder
{
    public function run(): void
    {
        $cats = Category::all()->keyBy('name');

        $dishes = [
            // Entradas Celestiales
            [
                'category_name' => 'Entradas Celestiales',
                'name' => 'Carpaccio de Wagyu Premium',
                'description' => 'Finas láminas de buey Wagyu curado, acompañadas de lascas de trufa negra, rúcula fresca y aliño artesanal de mostaza antigua.',
                'price' => 24.50,
                'image_url' => 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=600&q=80',
                'is_available' => true,
                'is_featured' => true,
            ],
            [
                'category_name' => 'Entradas Celestiales',
                'name' => 'Flores de Calabacín en Tempura',
                'description' => 'Rellenas de queso ricotta de cabra, piñones tostados y miel orgánica de tomillo silvestre.',
                'price' => 16.00,
                'image_url' => 'https://images.unsplash.com/photo-1608897013039-887f21d8c804?auto=format&fit=crop&w=600&q=80',
                'is_available' => true,
                'is_featured' => false,
            ],

            // Carnes & Brasa Premium
            [
                'category_name' => 'Carnes & Brasa Premium',
                'name' => 'Ribeye Black Angus a la Leña',
                'description' => '500g de Ribeye madurado durante 45 días, asado a fuego lento con madera de roble, acompañado de patatas rústicas.',
                'price' => 48.00,
                'image_url' => 'https://images.unsplash.com/photo-1546964124-0cce460f38ef?auto=format&fit=crop&w=600&q=80',
                'is_available' => true,
                'is_featured' => true,
            ],
            [
                'category_name' => 'Carnes & Brasa Premium',
                'name' => 'Lomo Fino de Cordero en Salsa de Vino Tinto',
                'description' => 'Tierno lomo de cordero lechal sobre cama de puré de chirivía con reducción de vino tinto Ribera del Duero.',
                'price' => 42.50,
                'image_url' => 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=600&q=80',
                'is_available' => true,
                'is_featured' => false,
            ],

            // Mariscos del Cantábrico
            [
                'category_name' => 'Mariscos del Cantábrico',
                'name' => 'Pulpo a la Parrilla con Hummus de Pimentón',
                'description' => 'Tentáculos de pulpo gallego crujiente sobre hummus suave de pimentón de la Vera, aceite de oliva virgen extra y sal Maldon.',
                'price' => 32.00,
                'image_url' => 'https://images.unsplash.com/photo-1565557623262-b51c2513a641?auto=format&fit=crop&w=600&q=80',
                'is_available' => true,
                'is_featured' => true,
            ],
            [
                'category_name' => 'Mariscos del Cantábrico',
                'name' => 'Tartar de Atún Aleta Azul con Aguacate',
                'description' => 'Atún rojo cortado a cuchillo con jengibre fresco, aceite de sésamo tostado, aguacate cremoso y esferificaciones de soja.',
                'price' => 28.50,
                'image_url' => 'https://images.unsplash.com/photo-1574484284002-952d92456975?auto=format&fit=crop&w=600&q=80',
                'is_available' => true,
                'is_featured' => false,
            ],

            // Postres de Autor
            [
                'category_name' => 'Postres de Autor',
                'name' => 'Tarta de Queso de Cabra y Trufa',
                'description' => 'Textura cremosa y fluida con un toque sutil de trufa blanca de Piamonte, sobre base de galleta artesanal de mantequilla.',
                'price' => 12.00,
                'image_url' => 'https://images.unsplash.com/photo-1508737027454-e6454ef45afd?auto=format&fit=crop&w=600&q=80',
                'is_available' => true,
                'is_featured' => false,
            ],
            [
                'category_name' => 'Postres de Autor',
                'name' => 'Volcán de Chocolate Belga con Pistacho',
                'description' => 'Bizcocho fluido de chocolate negro Callebaut al 70%, corazón fundido y helado artesanal de pistacho de Sicilia.',
                'price' => 14.00,
                'image_url' => 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?auto=format&fit=crop&w=600&q=80',
                'is_available' => true,
                'is_featured' => true,
            ],

            // Bebidas & Coctelería Restaurante
            [
                'category_name' => 'Bebidas & Coctelería Restaurante',
                'name' => 'Gin Tonic Restaurante Signature',
                'description' => 'Ginebra premium infusionada con botánicos selectos, frutos del bosque, tónica artesanal y copos de oro comestible de 24k.',
                'price' => 18.00,
                'image_url' => 'https://images.unsplash.com/photo-1524361120530-94332302488a?auto=format&fit=crop&w=600&q=80',
                'is_available' => true,
                'is_featured' => true,
            ],
            [
                'category_name' => 'Bebidas & Coctelería Restaurante',
                'name' => 'Mojito Cítrico de Hierbabuena',
                'description' => 'Ron añejo blanco, zumo de lima recién exprimido, hojas de menta orgánica maceradas y un toque de azúcar de caña.',
                'price' => 12.50,
                'image_url' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=600&q=80',
                'is_available' => true,
                'is_featured' => false,
            ],
        ];

        foreach ($dishes as $dish) {
            $category = $cats[$dish['category_name']] ?? null;

            if ($category) {
                Dish::create([
                    'category_id' => $category->id,
                    'name' => $dish['name'],
                    'slug' => Str::slug($dish['name']),
                    'description' => $dish['description'],
                    'price' => $dish['price'],
                    'image_url' => $dish['image_url'],
                    'is_available' => $dish['is_available'],
                    'is_featured' => $dish['is_featured'],
                ]);
            }
        }
    }
}
