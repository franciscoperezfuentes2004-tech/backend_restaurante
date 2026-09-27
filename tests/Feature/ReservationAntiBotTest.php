<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

class ReservationAntiBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.turnstile.required' => true]);
        RateLimiter::clear('127.0.0.1');

        \App\Models\RestaurantSetting::create([
            'schedule' => [
                ['day' => 'Domingo', 'open' => true, 'active' => 1],
                ['day' => 'Lunes', 'open' => true, 'active' => 1],
                ['day' => 'Martes', 'open' => true, 'active' => 1],
                ['day' => 'Miércoles', 'open' => true, 'active' => 1],
                ['day' => 'Jueves', 'open' => true, 'active' => 1],
                ['day' => 'Viernes', 'open' => true, 'active' => 1],
                ['day' => 'Sábado', 'open' => true, 'active' => 1],
            ],
        ]);
    }

    /**
     * Test 1: Rate Limiting throttle:3,1 bloquea la 4ta petición con HTTP 429 Too Many Requests.
     */
    public function test_rate_limiter_blocks_fourth_request_with_429(): void
    {
        $payload = [
            'nombre'          => 'Usuario Humano',
            'telefono'        => '7441234567',
            'fecha'           => now()->addDay()->toDateString(),
            'hora'            => '15:00',
            'personas'        => 2,
            'turnstile_token' => 'test-valid-turnstile-token',
        ];

        // 3 peticiones permitidas
        $res1 = $this->postJson('/api/reservations', $payload);
        $res1->assertStatus(201);

        $res2 = $this->postJson('/api/reservations', $payload);
        $res2->assertStatus(201);

        $res3 = $this->postJson('/api/reservations', $payload);
        $res3->assertStatus(201);

        // La 4ta petición en la misma ventana de 1 minuto DEBE devolver 429
        $res4 = $this->postJson('/api/reservations', $payload);
        $res4->assertStatus(429);
    }

    /**
     * Test 2: Petición pública sin token de Turnstile es rechazada con HTTP 422.
     */
    public function test_public_reservation_requires_turnstile_token(): void
    {
        $payload = [
            'nombre'   => 'Bot Sin Token',
            'telefono' => '7441234567',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '15:00',
            'personas' => 2,
        ];

        $res = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.10'])
                    ->postJson('/api/reservations', $payload);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['turnstile_token']);
    }

    /**
     * Test 3: Petición pública con token inválido de Turnstile es rechazada con HTTP 422.
     */
    public function test_invalid_turnstile_token_is_rejected(): void
    {
        $payload = [
            'nombre'          => 'Bot Token Falso',
            'telefono'        => '7441234567',
            'fecha'           => now()->addDay()->toDateString(),
            'hora'            => '15:00',
            'personas'        => 2,
            'turnstile_token' => 'test-invalid-turnstile-token',
        ];

        $res = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.11'])
                    ->postJson('/api/reservations', $payload);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['turnstile_token']);
    }

    /**
     * Test 4: Petición pública con token válido de Turnstile es aceptada (HTTP 201).
     */
    public function test_valid_turnstile_token_is_accepted(): void
    {
        $payload = [
            'nombre'          => 'Cliente Legitimo',
            'telefono'        => '7441234567',
            'fecha'           => now()->addDay()->toDateString(),
            'hora'            => '15:00',
            'personas'        => 2,
            'turnstile_token' => 'test-valid-turnstile-token',
        ];

        $res = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.12'])
                    ->postJson('/api/reservations', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('nombre', 'Cliente Legitimo');
    }

    /**
     * Test 5: Petición con cf-turnstile-response estándar es aceptada.
     */
    public function test_cf_turnstile_response_alias_is_accepted(): void
    {
        $payload = [
            'nombre'                => 'Cliente Con Alias Cloudflare',
            'telefono'              => '7441234567',
            'fecha'                 => now()->addDay()->toDateString(),
            'hora'                  => '16:00',
            'personas'              => 4,
            'cf-turnstile-response' => 'test-valid-turnstile-token',
        ];

        $res = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.13'])
                    ->postJson('/api/reservations', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('nombre', 'Cliente Con Alias Cloudflare');
    }

    /**
     * Test 6: Bot detectado por señuelo Honeypot (_hp_website) es rechazado con HTTP 422.
     */
    public function test_honeypot_field_blocks_bot(): void
    {
        $payload = [
            'nombre'          => 'Bot Automatizado',
            'telefono'        => '7441234567',
            'fecha'           => now()->addDay()->toDateString(),
            'hora'            => '15:00',
            'personas'        => 2,
            'turnstile_token' => 'test-valid-turnstile-token',
            '_hp_website'     => 'https://bot-spam-advertising.com',
        ];

        $res = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.14'])
                    ->postJson('/api/reservations', $payload);

        $res->assertStatus(422)
            ->assertJsonPath('message', 'Acceso denegado. Solicitud automatizada detectada por Honeypot.');
    }

    /**
     * Test 7: Bot detectado por señuelo alternativo Honeypot (website_url_hp).
     */
    public function test_alternate_honeypot_field_blocks_bot(): void
    {
        $payload = [
            'nombre'          => 'Spam Bot',
            'telefono'        => '7441234567',
            'fecha'           => now()->addDay()->toDateString(),
            'hora'            => '15:00',
            'personas'        => 2,
            'turnstile_token' => 'test-valid-turnstile-token',
            'website_url_hp'  => 'https://spam.org',
        ];

        $res = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.15'])
                    ->postJson('/api/reservations', $payload);

        $res->assertStatus(422)
            ->assertJsonPath('message', 'Acceso denegado. Solicitud automatizada detectada por Honeypot.');
    }

    /**
     * Test 8: Usuario administrativo autenticado en panel no requiere Turnstile token.
     */
    public function test_authenticated_admin_does_not_require_turnstile(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $payload = [
            'nombre'   => 'Reserva Hecha Por Admin',
            'telefono' => '7441234567',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '19:00',
            'personas' => 6,
        ];

        $res = $this->postJson('/api/admin/reservations', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('nombre', 'Reserva Hecha Por Admin');
    }

    /**
     * Test 9: En entorno local, la verificación de Turnstile se omite automáticamente.
     */
    public function test_local_environment_bypasses_turnstile_requirement(): void
    {
        $this->app['env'] = 'local';

        $payload = [
            'nombre'   => 'Cliente Local Sin Token',
            'telefono' => '7441234567',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '18:00',
            'personas' => 2,
        ];

        $res = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.16'])
                    ->postJson('/api/reservations', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('nombre', 'Cliente Local Sin Token');
    }
}