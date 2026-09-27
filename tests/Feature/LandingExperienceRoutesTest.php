<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingExperienceRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_statistics_experiences(): void
    {
        Review::create([
            'folio' => 'COM202609170001',
            'nombre' => 'Carlos Gomez',
            'correo' => 'carlos@example.com',
            'telefono' => '5551234567',
            'rating' => 5,
            'comentario' => 'Excelente comida y servicio',
            'is_approved' => true,
        ]);

        Review::create([
            'folio' => 'COM202609170002',
            'nombre' => 'Maria Lopez',
            'correo' => 'maria@example.com',
            'telefono' => '5551234568',
            'rating' => 4,
            'comentario' => 'Muy buen ambiente',
            'is_approved' => true,
        ]);

        $response = $this->getJson('/api/statistics/experiences');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_opiniones',
                'promedio_general',
                'cinco_estrellas',
                'cuatro_estrellas',
                'distribucion',
            ]);
    }

    public function test_can_get_reviews_landing_with_maximum_15_limit(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            Review::create([
                'folio'       => 'COM20260917' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'nombre'      => "Cliente {$i}",
                'correo'      => "cliente{$i}@example.com",
                'telefono'    => "555123456{$i}",
                'rating'      => 5,
                'comentario'  => "Excelente comida {$i}",
                'is_approved' => true,
                'estado'      => 'aprobado',
            ]);
        }

        // Crear una no aprobada que no debe salir
        Review::create([
            'folio'       => 'COM202609179999',
            'nombre'      => 'Cliente Rechazado',
            'correo'      => 'rechazado@example.com',
            'telefono'    => '5559999999',
            'rating'      => 1,
            'comentario'  => 'Spam',
            'is_approved' => false,
            'estado'      => 'pendiente',
        ]);

        $response = $this->getJson('/api/reviews/landing');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(15, $data);
    }
}
