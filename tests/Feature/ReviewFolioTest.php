<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\Testimonial;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewFolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-17 15:30:00'));
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test 1: El helper genera el folio con formato COMYYYYMMDD0063 sin guiones.
     */
    public function test_generador_folio_resena_crea_formato_correcto_con_prefijo_com(): void
    {
        $review = new Review();
        $review->id = 63;
        $review->created_at = Carbon::parse('2026-09-17 12:00:00');

        $folio = Review::generarFolioResena($review);

        $this->assertEquals('COM202609170063', $folio);
        $this->assertStringNotContainsString('-', $folio);
        $this->assertStringStartsWith('COM20260917', $folio);
        $this->assertMatchesRegularExpression('/^COM\d{12}$/', $folio);
    }

    /**
     * Test 2: El endpoint POST /api/reviews guarda y retorna el folio con prefijo COM.
     */
    public function test_endpoint_reviews_store_asigna_folio_com(): void
    {
        $payload = [
            'nombre'     => 'Fernanda Morales',
            'telefono'   => '7441234567',
            'correo'     => 'fernanda@test.com',
            'rating'     => 5,
            'comentario' => 'La comida y atención fueron excelentes.',
        ];

        $response = $this->postJson('/api/reviews', $payload);

        $response->assertStatus(201);
        $reviewId = $response->json('data.id') ?? $response->json('review.id');
        $this->assertNotNull($reviewId);

        $expectedFolio = 'COM20260917' . str_pad($reviewId, 4, '0', STR_PAD_LEFT);
        
        $savedReview = Review::find($reviewId);
        $this->assertEquals($expectedFolio, $savedReview->folio);
    }

    /**
     * Test 3: El endpoint POST /api/testimonials guarda la reseña sincronizada con folio COM.
     */
    public function test_endpoint_testimonials_store_asigna_folio_com(): void
    {
        $payload = [
            'nombre'     => 'Roberto Garza',
            'telefono'   => '7449876543',
            'correo'     => 'roberto@test.com',
            'rating'     => 4,
            'comentario' => 'Muy buen servicio en terraza.',
        ];

        $response = $this->postJson('/api/testimonials', $payload);

        $response->assertStatus(201);
        $review = Review::where('correo', 'roberto@test.com')->first();
        $this->assertNotNull($review);
        
        $expectedFolio = 'COM20260917' . str_pad($review->id, 4, '0', STR_PAD_LEFT);
        $this->assertEquals($expectedFolio, $review->folio);
        $this->assertStringStartsWith('COM', $review->folio);
    }
}
