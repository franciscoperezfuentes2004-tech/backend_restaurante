<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Entradas Celestiales',
                'description' => 'Aperitivos gourmet y entremeses diseñados para deleitar el paladar antes del plato fuerte.',
                'active' => true,
            ],
            [
                'name' => 'Carnes & Brasa Premium',
                'description' => 'Cortes finos de res de la más alta calidad, asados al carbón y sazonados a la perfección.',
                'active' => true,
            ],
            [
                'name' => 'Mariscos del Cantábrico',
                'description' => 'Pescados y mariscos frescos traídos diariamente, preparados con técnicas culinarias refinadas.',
                'active' => true,
            ],
            [
                'name' => 'Postres de Autor',
                'description' => 'Creaciones dulces artesanales que combinan texturas y sabores sofisticados.',
                'active' => true,
            ],
            [
                'name' => 'Bebidas & Coctelería Aurum',
                'description' => 'Bebidas refrescantes y cócteles insignia creados por nuestros mixólogos profesionales.',
                'active' => true,
            ],
        ];

        foreach ($categories as $cat) {
            $cat['slug'] = Str::slug($cat['name']);
            Category::create($cat);
        }
    }
}
