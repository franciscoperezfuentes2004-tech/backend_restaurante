<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewSafeAggregationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Prevención de división por cero cuando la base de datos no tiene reseñas.
     */
    public function test_prevents_division_by_zero_when_no_reviews_exist(): void
    {
        $stats = Review::getExperienceStats();

        $this->assertSame([
            'total'        => 0,
            'promedio'     => 0,
            'satisfaccion' => 0,
        ], $stats);

        $response = $this->getJson('/api/reviews/stats');
        $response->assertStatus(200)
            ->assertJson([
                'stats_experiencias' => [
                    'total'        => 0,
                    'promedio'     => 0,
                    'satisfaccion' => 0,
                ],
            ]);

        $testimonialStatsResponse = $this->getJson('/api/testimonials/stats');
        $testimonialStatsResponse->assertStatus(200)
            ->assertJson([
                'stats_experiencias' => [
                    'total'        => 0,
                    'promedio'     => 0,
                    'satisfaccion' => 0,
                ],
            ]);
    }

    /**
     * Test 2: Moderación Zero-Trust (ignora reseñas pendientes, ocultas o reportadas).
     */
    public function test_zero_trust_moderation_ignores_unapproved_and_spam_reviews(): void
    {
        // Reseñas no aprobadas / spam
        Testimonial::create([
            'customer_name' => 'Bot 1',
            'comment'       => 'Spam link http://spam.com',
            'rating'        => 5,
            'status'        => 'pendiente',
            'is_approved'   => false,
        ]);

        Testimonial::create([
            'customer_name' => 'Bot 2',
            'comment'       => 'Fake review',
            'rating'        => 1,
            'status'        => 'reportada',
            'is_approved'   => false,
        ]);

        Testimonial::create([
            'customer_name' => 'Inappropriate',
            'comment'       => 'Offensive content',
            'rating'        => 5,
            'status'        => 'oculta',
            'is_approved'   => false,
            'hidden_reason' => 'Lenguaje inapropiado',
        ]);

        $stats = Review::getExperienceStats();

        // No debe contar ninguna reseña sin moderación
        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['promedio']);
        $this->assertEquals(0, $stats['satisfaccion']);

        $response = $this->getJson('/api/reviews/stats');
        $response->assertStatus(200)
            ->assertJson([
                'stats_experiencias' => [
                    'total'        => 0,
                    'promedio'     => 0,
                    'satisfaccion' => 0,
                ],
            ]);
    }

    /**
     * Test 3: Cálculo exacto de métricas con reseñas aprobadas por el administrador.
     */
    public function test_calculates_correct_average_and_satisfaction_percentage(): void
    {
        // 4 Reseñas aprobadas:
        // 5 estrellas, 5 estrellas, 4 estrellas, 2 estrellas
        // Total = 4
        // Promedio = (5 + 5 + 4 + 2) / 4 = 16 / 4 = 4.0
        // Satisfechos (4 o 5 estrellas) = 3 de 4 => (3 / 4) * 100 = 75%
        Testimonial::create([
            'customer_name' => 'Cliente Feliz 1',
            'comment'       => 'Excelente comida y servicio',
            'rating'        => 5,
            'status'        => 'aprobada',
            'is_approved'   => true,
        ]);

        Testimonial::create([
            'customer_name' => 'Cliente Feliz 2',
            'comment'       => 'La terraza es maravillosa',
            'rating'        => 5,
            'status'        => 'aprobada',
            'is_approved'   => true,
        ]);

        Testimonial::create([
            'customer_name' => 'Cliente Satisfecho',
            'comment'       => 'Muy buena experiencia',
            'rating'        => 4,
            'status'        => 'aprobada',
            'is_approved'   => true,
        ]);

        Testimonial::create([
            'customer_name' => 'Cliente Neutral',
            'comment'       => 'Tardó un poco el servicio',
            'rating'        => 2,
            'status'        => 'aprobada',
            'is_approved'   => true,
        ]);

        // Y agregamos una pendiente que NO debe alterar las estadísticas
        Testimonial::create([
            'customer_name' => 'Cliente Pendiente',
            'comment'       => 'Pendiente de moderar',
            'rating'        => 1,
            'status'        => 'pendiente',
            'is_approved'   => false,
        ]);

        $stats = Review::getExperienceStats();

        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(4.0, $stats['promedio']);
        $this->assertEquals(75, $stats['satisfaccion']);

        $response = $this->getJson('/api/reviews/stats');
        $response->assertStatus(200)
            ->assertJson([
                'stats_experiencias' => [
                    'total'        => 4,
                    'promedio'     => 4.0,
                    'satisfaccion' => 75,
                ],
            ]);
    }

    /**
     * Test 4: Integración con /api/testimonials y /api/landing-config
     */
    public function test_endpoints_expose_stats_experiencias_payload(): void
    {
        Testimonial::create([
            'customer_name' => 'Ana Garcia',
            'comment'       => 'Insuperable',
            'rating'        => 5,
            'status'        => 'aprobada',
            'is_approved'   => true,
        ]);

        // Testimonials endpoint
        $resTestimonials = $this->getJson('/api/testimonials');
        $resTestimonials->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta',
                'stats_experiencias' => [
                    'total',
                    'promedio',
                    'satisfaccion',
                ],
                'summary' => [
                    'total_count',
                    'avg_rating',
                    'satisfaction_percentage',
                ],
            ]);

        $this->assertEquals(1, $resTestimonials->json('stats_experiencias.total'));
        $this->assertEquals(5, $resTestimonials->json('stats_experiencias.promedio'));
        $this->assertEquals(100, $resTestimonials->json('stats_experiencias.satisfaccion'));

        // Landing-config endpoint
        $resLanding = $this->getJson('/api/landing-config');
        $resLanding->assertStatus(200)
            ->assertJsonStructure([
                'stats_experiencias' => [
                    'total',
                    'promedio',
                    'satisfaccion',
                ],
            ]);

        $this->assertEquals(1, $resLanding->json('stats_experiencias.total'));
    }

    /**
     * Test 5: Sintaxis exacta del usuario con Review::where('is_approved', true)->get()
     */
    public function test_review_model_syntax_and_behavior(): void
    {
        Review::create([
            'customer_name' => 'Pedro',
            'telefono'      => '7441112233',
            'correo'        => 'pedro@example.com',
            'comment'       => 'Gran atención',
            'rating'        => 5,
            'status'        => 'aprobada',
            'is_approved'   => true,
        ]);

        $reviews = Review::where('is_approved', true)->get();
        $this->assertCount(1, $reviews);

        $totalReviews = $reviews->count();
        $averageRating = $totalReviews > 0 ? round($reviews->avg('rating'), 1) : 0;
        $happyReviews = $reviews->whereIn('rating', [4, 5])->count();
        $satisfaction = $totalReviews > 0 ? round(($happyReviews / $totalReviews) * 100) : 0;

        $this->assertEquals(1, $totalReviews);
        $this->assertEquals(5, $averageRating);
        $this->assertEquals(100, $satisfaction);
    }
}
