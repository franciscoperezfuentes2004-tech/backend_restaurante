<?php

namespace Tests\Feature;

use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewMetricsDistributionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Calcula correctamente el promedio y la distribución del 5 al 1 con porcentajes.
     */
    public function test_metricas_resenas_calcula_distribucion_y_promedio(): void
    {
        // 2 reseñas de 5 estrellas
        Review::create(['nombre' => 'User 1', 'telefono' => '7441112233', 'correo' => 'u1@test.com', 'rating' => 5, 'comentario' => 'Excelente', 'is_approved' => true]);
        Review::create(['nombre' => 'User 2', 'telefono' => '7442223344', 'correo' => 'u2@test.com', 'rating' => 5, 'comentario' => 'Increíble', 'is_approved' => true]);

        // 1 reseña de 4 estrellas
        Review::create(['nombre' => 'User 3', 'telefono' => '7443334455', 'correo' => 'u3@test.com', 'rating' => 4, 'comentario' => 'Muy bueno', 'is_approved' => true]);

        // 1 reseña de 3 estrellas
        Review::create(['nombre' => 'User 4', 'telefono' => '7444445566', 'correo' => 'u4@test.com', 'rating' => 3, 'comentario' => 'Regular', 'is_approved' => true]);

        // 0 reseñas de 2 estrellas

        // 0 reseñas de 1 estrella

        // Total = 4 reseñas. Suma = 5+5+4+3 = 17. Promedio = 17 / 4 = 4.25 -> 4.3
        // Nivel 5: 2/4 = 50%
        // Nivel 4: 1/4 = 25%
        // Nivel 3: 1/4 = 25%
        // Nivel 2: 0/4 = 0%
        // Nivel 1: 0/4 = 0%

        $response = $this->getJson('/api/metricas-resenas');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'promedio',
                'total',
                'distribucion' => [
                    '*' => ['nivel', 'cantidad', 'porcentaje']
                ]
            ]);

        $this->assertEquals(4, $response->json('total'));
        $this->assertEquals(4.3, round((float) $response->json('promedio'), 1));

        $distribucion = $response->json('distribucion');
        $this->assertCount(5, $distribucion);

        $this->assertEquals(['nivel' => 5, 'cantidad' => 2, 'porcentaje' => 50], $distribucion[0]);
        $this->assertEquals(['nivel' => 4, 'cantidad' => 1, 'porcentaje' => 25], $distribucion[1]);
        $this->assertEquals(['nivel' => 3, 'cantidad' => 1, 'porcentaje' => 25], $distribucion[2]);
        $this->assertEquals(['nivel' => 2, 'cantidad' => 0, 'porcentaje' => 0], $distribucion[3]);
        $this->assertEquals(['nivel' => 1, 'cantidad' => 0, 'porcentaje' => 0], $distribucion[4]);
    }

    /**
     * Test 2: Prevención de división por cero cuando no existen reseñas registradas.
     */
    public function test_metricas_resenas_previene_division_por_cero_sin_resenas(): void
    {
        $response = $this->getJson('/api/metricas-resenas');

        $response->assertStatus(200)
            ->assertJson([
                'promedio' => 0,
                'total'    => 0,
                'distribucion' => [
                    ['nivel' => 5, 'cantidad' => 0, 'porcentaje' => 0],
                    ['nivel' => 4, 'cantidad' => 0, 'porcentaje' => 0],
                    ['nivel' => 3, 'cantidad' => 0, 'porcentaje' => 0],
                    ['nivel' => 2, 'cantidad' => 0, 'porcentaje' => 0],
                    ['nivel' => 1, 'cantidad' => 0, 'porcentaje' => 0],
                ]
            ]);
    }

    /**
     * Test 3: Las rutas de alias devuelven la misma distribución completa.
     */
    public function test_aliases_devuelven_misma_estructura(): void
    {
        Review::create(['nombre' => 'Cliente A', 'telefono' => '7449998877', 'correo' => 'ca@test.com', 'rating' => 5, 'comentario' => 'Genial', 'is_approved' => true]);

        $resReviewsMetricas = $this->getJson('/api/reviews/metricas-resenas');
        $resReviewsMetricas->assertStatus(200);
        $this->assertEquals(1, $resReviewsMetricas->json('total'));
        $this->assertEquals(5, $resReviewsMetricas->json('distribucion.0.nivel'));
        $this->assertEquals(1, $resReviewsMetricas->json('distribucion.0.cantidad'));
        $this->assertEquals(100, $resReviewsMetricas->json('distribucion.0.porcentaje'));

        $resDistribucion = $this->getJson('/api/reviews/distribucion');
        $resDistribucion->assertStatus(200);
        $this->assertEquals(1, $resDistribucion->json('total'));
    }
}
