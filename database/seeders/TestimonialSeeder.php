<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            [
                'customer_name' => 'Carlos Mendoza',
                'comment' => 'El Ribeye Black Angus es sencillamente espectacular. El sabor a leña de roble le da un toque único. El servicio en mesa es impecable.',
                'rating' => 5,
                'is_approved' => true,
            ],
            [
                'customer_name' => 'Sofía Alarcón',
                'comment' => 'Una experiencia gastronómica de primer nivel. El Carpaccio de Wagyu se deshace en la boca. Volveremos sin duda.',
                'rating' => 5,
                'is_approved' => true,
            ],
            [
                'customer_name' => 'Alejandro Ruiz',
                'comment' => 'El ambiente es precioso, muy íntimo y elegante. Recomiendo ampliamente el cóctel Gin Tonic Restaurante Signature.',
                'rating' => 4,
                'is_approved' => true,
            ],
            [
                'customer_name' => 'Clara Belmonte',
                'comment' => 'La tarta de queso de cabra con trufa es de otro planeta. Es el postre perfecto para cerrar una cena inolvidable.',
                'rating' => 5,
                'is_approved' => true,
            ],
            [
                'customer_name' => 'Daniel Ortega',
                'comment' => 'El pulpo estaba un poco duro hoy, pero el hummus de pimentón que lo acompañaba estaba riquísimo. Buen servicio general.',
                'rating' => 3,
                'is_approved' => true, // Approved but constructive criticism
            ],
            [
                'customer_name' => 'Laura Espinoza',
                'comment' => 'Me encantó la atención y la rapidez al servir la comida. Todo delicioso.',
                'rating' => 5,
                'is_approved' => false, // Pending moderation for testing
            ],
        ];

        foreach ($testimonials as $t) {
            Testimonial::create($t);
        }
    }
}
