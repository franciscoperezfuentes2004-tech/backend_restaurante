<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\Testimonial;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-17 16:30:00'));
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test 1: El administrador puede responder a una reseña y se guarda respuesta_admin y fecha_respuesta.
     */
    public function test_admin_puede_responder_resena_y_guarda_campos_en_bd(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $review = Review::create([
            'nombre'      => 'Carlos Slim',
            'telefono'    => '7441112233',
            'correo'      => 'carlos@test.com',
            'rating'      => 5,
            'comentario'  => 'El mejor corte de carne de la ciudad.',
            'is_approved' => true,
        ]);

        $payload = [
            'respuesta_admin' => '¡Muchas gracias por tu visita Carlos! Te esperamos pronto de nuevo.',
        ];

        $response = $this->actingAs($admin)->postJson("/api/admin/reviews/{$review->id}/responder", $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'mensaje' => 'Respuesta publicada exitosamente',
            'resena'  => [
                'id'              => $review->id,
                'respuesta_admin' => '¡Muchas gracias por tu visita Carlos! Te esperamos pronto de nuevo.',
            ]
        ]);

        $review->refresh();
        $this->assertEquals('¡Muchas gracias por tu visita Carlos! Te esperamos pronto de nuevo.', $review->respuesta_admin);
        $this->assertNotNull($review->fecha_respuesta);
        $this->assertEquals('2026-09-17 16:30:00', $review->fecha_respuesta->format('Y-m-d H:i:s'));
    }

    /**
     * Test 2: El endpoint show devuelve la respuesta_admin y fecha_respuesta.
     */
    public function test_admin_review_show_retorna_respuesta_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $review = Review::create([
            'nombre'          => 'Diana Prince',
            'telefono'        => '7442223344',
            'correo'          => 'diana@test.com',
            'rating'          => 4,
            'comentario'      => 'Excelente ambiente y música agradable.',
            'respuesta_admin' => 'Nos alegra que hayas disfrutado la música y ambiente.',
            'fecha_respuesta' => Carbon::now(),
            'is_approved'     => true,
        ]);

        $response = $this->actingAs($admin)->getJson("/api/admin/reviews/{$review->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('respuesta_admin', 'Nos alegra que hayas disfrutado la música y ambiente.');
        $response->assertJsonPath('respuesta.texto', 'Nos alegra que hayas disfrutado la música y ambiente.');
    }

    /**
     * Test 3: Validación falla si no se envía la respuesta.
     */
    public function test_responder_resena_valida_campo_requerido(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $review = Review::create([
            'nombre'      => 'Mario Bros',
            'telefono'    => '7443334455',
            'correo'      => 'mario@test.com',
            'rating'      => 5,
            'comentario'  => 'Super ricos los champiñones.',
            'is_approved' => true,
        ]);

        $response = $this->actingAs($admin)->postJson("/api/admin/reviews/{$review->id}/responder", [
            'respuesta_admin' => '',
        ]);

        $response->assertStatus(422);
    }
}