<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
        RateLimiter::clear('reservaciones');
        RateLimiter::clear('resenas');
    }

    /**
     * Test 1: Rate Limiter para Login bloquea al 6to intento por minuto (throttle:login).
     */
    public function test_login_rate_limiter_blocks_sixth_attempt_with_429(): void
    {
        $payload = [
            'email'    => 'admin@aurum.com',
            'password' => 'wrongpassword',
        ];

        // 5 intentos permitidos
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/login', $payload);
            $this->assertNotEquals(429, $response->status(), "El intento {$i} no debió ser bloqueado con 429.");
        }

        // El 6to intento debe recibir 429 Too Many Requests
        $res6 = $this->postJson('/api/login', $payload);
        $res6->assertStatus(429);
    }

    /**
     * Test 2: Rate Limiter para Reservaciones bloquea al 4to intento por minuto (throttle:reservaciones).
     */
    public function test_reservations_rate_limiter_blocks_fourth_attempt_with_429(): void
    {
        $payload = [
            'nombre'          => 'Cliente Prueba',
            'telefono'        => '7441112233',
            'fecha'           => now()->addDay()->toDateString(),
            'hora'            => '14:00',
            'personas'        => 2,
            'turnstile_token' => 'test-valid-turnstile-token',
        ];

        // 3 intentos permitidos
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->postJson('/api/reservaciones', $payload);
            $this->assertNotEquals(429, $response->status(), "El intento {$i} no debió ser bloqueado con 429.");
        }

        // El 4to intento debe ser bloqueado con 429
        $res4 = $this->postJson('/api/reservaciones', $payload);
        $res4->assertStatus(429);
    }

    /**
     * Test 3: Rate Limiter para Reseñas bloquea al 3er intento por minuto (throttle:resenas).
     */
    public function test_reviews_rate_limiter_blocks_third_attempt_with_429(): void
    {
        $payload = [
            'nombre'          => 'Cliente Reseña',
            'telefono'        => '7449998877',
            'rating'          => 5,
            'comentario'      => 'Excelente servicio y platillos deliciosos.',
            'turnstile_token' => 'test-valid-turnstile-token',
        ];

        // 2 intentos permitidos
        for ($i = 1; $i <= 2; $i++) {
            $response = $this->postJson('/api/resenas', $payload);
            $this->assertNotEquals(429, $response->status(), "El intento {$i} no debió ser bloqueado con 429.");
        }

        // El 3er intento debe ser bloqueado con 429
        $res3 = $this->postJson('/api/resenas', $payload);
        $res3->assertStatus(429);
    }
}
