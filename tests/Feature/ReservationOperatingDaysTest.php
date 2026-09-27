<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\RestaurantSetting;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReservationOperatingDaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        config(['services.turnstile.required' => false]);
    }

    /**
     * Test 1: Los endpoints /api/settings y /api/reservations/schedule devuelven active_days como arreglo numérico (0-6).
     */
    public function test_settings_and_schedule_endpoints_return_active_days_numeric_array(): void
    {
        RestaurantSetting::create([
            'schedule' => [
                ['day' => 'Domingo', 'open' => false, 'active' => 0],
                ['day' => 'Lunes', 'open' => true, 'active' => 1],
                ['day' => 'Martes', 'open' => true, 'active' => 1],
                ['day' => 'Miércoles', 'open' => true, 'active' => 1],
                ['day' => 'Jueves', 'open' => true, 'active' => 1],
                ['day' => 'Viernes', 'open' => true, 'active' => 1],
                ['day' => 'Sábado', 'open' => true, 'active' => 1],
            ],
        ]);

        // 1. GET /api/settings
        $resSettings = $this->getJson('/api/settings');
        $resSettings->assertStatus(200)
                    ->assertJsonStructure([
                        'active_days',
                        'schedule',
                    ]);

        $activeDays = $resSettings->json('active_days');
        $this->assertIsArray($activeDays);
        $this->assertEquals([1, 2, 3, 4, 5, 6], $activeDays);

        // 2. GET /api/reservations/schedule
        $resSchedule = $this->getJson('/api/reservations/schedule');
        $resSchedule->assertStatus(200)
                    ->assertJson([
                        'active_days' => [1, 2, 3, 4, 5, 6],
                    ]);

        // 3. GET /api/settings/schedule
        $resSettingsSchedule = $this->getJson('/api/settings/schedule');
        $resSettingsSchedule->assertStatus(200)
                            ->assertJson([
                                'active_days' => [1, 2, 3, 4, 5, 6],
                            ]);

        // 4. GET /api/landing-config
        $resLandingConfig = $this->getJson('/api/landing-config');
        $resLandingConfig->assertStatus(200)
                         ->assertJson([
                             'active_days' => [1, 2, 3, 4, 5, 6],
                         ]);

        // 5. GET /api/landing/config
        $resLandingSubConfig = $this->getJson('/api/landing/config');
        $resLandingSubConfig->assertStatus(200)
                            ->assertJson([
                                'active_days' => [1, 2, 3, 4, 5, 6],
                            ]);
    }

    /**
     * Test 2: Validación Zero-Trust rechaza con 422 cualquier intento de reserva en un día cerrado.
     */
    public function test_zero_trust_rejects_reservation_on_closed_day(): void
    {
        // Restaurante cerrado los domingos (0) y lunes (1)
        RestaurantSetting::create([
            'schedule' => [
                ['day' => 'Domingo', 'open' => false, 'active' => 0],
                ['day' => 'Lunes', 'open' => false, 'active' => 0],
                ['day' => 'Martes', 'open' => true, 'active' => 1],
                ['day' => 'Miércoles', 'open' => true, 'active' => 1],
                ['day' => 'Jueves', 'open' => true, 'active' => 1],
                ['day' => 'Viernes', 'open' => true, 'active' => 1],
                ['day' => 'Sábado', 'open' => true, 'active' => 1],
            ],
        ]);

        // Próximo domingo (Día 0 - Cerrado)
        $sunday = Carbon::now()->next(Carbon::SUNDAY)->toDateString();

        $payload = [
            'nombre'   => 'Cliente Infiltrado',
            'telefono' => '7441234567',
            'fecha'    => $sunday,
            'hora'     => '14:00',
            'personas' => 2,
        ];

        $res = $this->postJson('/api/reservations', $payload);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['fecha']);

        $errorMsg = $res->json('errors.fecha.0');
        $this->assertStringContainsString('El restaurante se encuentra cerrado en el día seleccionado', $errorMsg);
    }

    /**
     * Test 3: Validación Zero-Trust acepta la reserva con HTTP 201 en un día operativo de servicio.
     */
    public function test_zero_trust_accepts_reservation_on_open_day(): void
    {
        RestaurantSetting::create([
            'schedule' => [
                ['day' => 'Domingo', 'open' => false, 'active' => 0],
                ['day' => 'Lunes', 'open' => true, 'active' => 1],
                ['day' => 'Martes', 'open' => true, 'active' => 1],
                ['day' => 'Miércoles', 'open' => true, 'active' => 1],
                ['day' => 'Jueves', 'open' => true, 'active' => 1],
                ['day' => 'Viernes', 'open' => true, 'active' => 1],
                ['day' => 'Sábado', 'open' => true, 'active' => 1],
            ],
        ]);

        // Próximo viernes (Día 5 - Abierto)
        $friday = Carbon::now()->next(Carbon::FRIDAY)->toDateString();

        $payload = [
            'nombre'   => 'Cliente Verificado',
            'telefono' => '7449876543',
            'fecha'    => $friday,
            'hora'     => '20:00',
            'personas' => 4,
        ];

        $res = $this->postJson('/api/reservations', $payload);

        $res->assertStatus(201)
            ->assertJson([
                'message' => 'Reserva solicitada con éxito',
            ]);

        $this->assertDatabaseHas('reservations', [
            'nombre'   => 'Cliente Verificado',
            'telefono' => '7449876543',
            'fecha'    => $friday,
        ]);
    }

    /**
     * Test 4: Comportamiento por defecto (Fallback) cuando la base de datos no tiene horarios configurados.
     * Fallback estándar: Lunes a Sábado abiertos [1, 2, 3, 4, 5, 6], Domingo (0) cerrado.
     */
    public function test_fallback_schedule_when_no_configuration_exists(): void
    {
        // Sin registros en restaurant_settings
        $this->assertEquals([1, 2, 3, 4, 5, 6], RestaurantSetting::getActiveOperatingDays());

        // Intentar reservar en domingo debe fallar
        $sunday = Carbon::now()->next(Carbon::SUNDAY)->toDateString();
        $resSunday = $this->postJson('/api/reservations', [
            'nombre'   => 'Cliente Domingo',
            'telefono' => '7441112233',
            'fecha'    => $sunday,
            'hora'     => '15:00',
            'personas' => 2,
        ]);
        $resSunday->assertStatus(422)
                  ->assertJsonValidationErrors(['fecha']);

        // Intentar reservar en miércoles debe tener éxito
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY)->toDateString();
        $resWednesday = $this->postJson('/api/reservations', [
            'nombre'   => 'Cliente Abierto',
            'telefono' => '7441112233',
            'fecha'    => $wednesday,
            'hora'     => '15:00',
            'personas' => 2,
        ]);
        $resWednesday->assertStatus(201);
    }

    /**
     * Test 5: Horario del panel administrativo (Lunes a Viernes ON, Sábado y Domingo OFF).
     * El backend debe devolver estrictamente: 'active_days' => [1, 2, 3, 4, 5]
     * y bloquear sábado y domingo con HTTP 422.
     */
    public function test_admin_schedule_from_screenshot_returns_active_days_1_to_5_and_blocks_saturday(): void
    {
        RestaurantSetting::create([
            'schedule' => [
                ['day' => 'Lunes', 'active' => 1, 'is_active' => 1, 'open' => '09:00', 'close' => '23:00'],
                ['day' => 'Martes', 'active' => 1, 'is_active' => 1, 'open' => '09:00', 'close' => '23:00'],
                ['day' => 'Miércoles', 'active' => 1, 'is_active' => 1, 'open' => '09:00', 'close' => '23:00'],
                ['day' => 'Jueves', 'active' => 1, 'is_active' => 1, 'open' => '09:00', 'close' => '23:00'],
                ['day' => 'Viernes', 'active' => 1, 'is_active' => 1, 'open' => '09:00', 'close' => '23:00'],
                ['day' => 'Sábado', 'active' => 0, 'is_active' => 0, 'open' => '13:00', 'close' => '23:00'],
                ['day' => 'Domingo', 'active' => 0, 'is_active' => 0, 'open' => '13:00', 'close' => '23:00'],
            ],
        ]);

        $this->assertEquals([1, 2, 3, 4, 5], RestaurantSetting::getActiveOperatingDays());

        // GET /api/landing-config
        $resLanding = $this->getJson('/api/landing-config');
        $resLanding->assertStatus(200)
                   ->assertJson([
                       'active_days' => [1, 2, 3, 4, 5],
                   ]);

        // GET /api/settings
        $resSettings = $this->getJson('/api/settings');
        $resSettings->assertStatus(200)
                    ->assertJson([
                        'active_days' => [1, 2, 3, 4, 5],
                    ]);

        // Intento de reservar en Sábado (Día 6 - Cerrado en panel)
        $saturday = Carbon::now()->next(Carbon::SATURDAY)->toDateString();
        $resSaturday = $this->postJson('/api/reservations', [
            'nombre'   => 'Cliente Intento Sabado',
            'telefono' => '7442223344',
            'fecha'    => $saturday,
            'hora'     => '14:00',
            'personas' => 2,
        ]);
        $resSaturday->assertStatus(422)
                    ->assertJsonValidationErrors(['fecha']);

        $this->assertStringContainsString(
            'El restaurante se encuentra cerrado en el día seleccionado',
            $resSaturday->json('errors.fecha.0')
        );

        // Intento de reservar en Domingo (Día 0 - Cerrado en panel)
        $sunday = Carbon::now()->next(Carbon::SUNDAY)->toDateString();
        $resSunday = $this->postJson('/api/reservations', [
            'nombre'   => 'Cliente Intento Domingo',
            'telefono' => '7442223344',
            'fecha'    => $sunday,
            'hora'     => '14:00',
            'personas' => 2,
        ]);
        $resSunday->assertStatus(422)
                  ->assertJsonValidationErrors(['fecha']);

        // Reserva en Jueves (Día 4 - Abierto en panel)
        $thursday = Carbon::now()->next(Carbon::THURSDAY)->toDateString();
        $resThursday = $this->postJson('/api/reservations', [
            'nombre'   => 'Cliente Jueves',
            'telefono' => '7442223344',
            'fecha'    => $thursday,
            'hora'     => '14:00',
            'personas' => 2,
        ]);
        $resThursday->assertStatus(201);
    }

    /**
     * Test 6: Endpoint devuelve diccionario 'horarios' indexado por número de día con open y close.
     */
    public function test_horarios_payload_contains_dictionary_with_open_and_close_per_day(): void
    {
        RestaurantSetting::create([
            'schedule' => [
                ['day' => 'Lunes', 'active' => 1, 'is_active' => 1, 'open' => '09:00', 'close' => '23:00'],
                ['day' => 'Martes', 'active' => 1, 'is_active' => 1, 'open' => '09:00', 'close' => '23:00'],
                ['day' => 'Miércoles', 'active' => 1, 'is_active' => 1, 'open' => '09:00', 'close' => '23:00'],
                ['day' => 'Jueves', 'active' => 1, 'is_active' => 1, 'open' => '09:00', 'close' => '23:00'],
                ['day' => 'Viernes', 'active' => 1, 'is_active' => 1, 'open' => '09:00', 'close' => '23:00'],
                ['day' => 'Sábado', 'active' => 0, 'is_active' => 0, 'open' => '13:00', 'close' => '23:00'],
                ['day' => 'Domingo', 'active' => 0, 'is_active' => 0, 'open' => '13:00', 'close' => '23:00'],
            ],
        ]);

        $res = $this->getJson('/api/landing-config');
        $res->assertStatus(200)
            ->assertJsonStructure([
                'horarios' => [
                    '1' => ['open', 'close'],
                    '2' => ['open', 'close'],
                    '3' => ['open', 'close'],
                    '4' => ['open', 'close'],
                    '5' => ['open', 'close'],
                ]
            ]);

        $horarios = $res->json('horarios');
        $this->assertEquals('09:00', $horarios['1']['open']);
        $this->assertEquals('23:00', $horarios['1']['close']);
        $this->assertArrayNotHasKey('0', $horarios);
        $this->assertArrayNotHasKey('6', $horarios);
    }

    /**
     * Test 7: Bloqueo de hora pasada para reservaciones el día de hoy (fecha == today).
     */
    public function test_zero_trust_blocks_past_time_when_reserving_today(): void
    {
        RestaurantSetting::create([
            'schedule' => [
                ['day' => 'Lunes', 'active' => 1, 'is_active' => 1, 'open' => '09:00', 'close' => '23:00'],
            ],
        ]);

        // Fijar el tiempo simulado a un Lunes a las 15:30
        Carbon::setTestNow(Carbon::parse('2026-09-14 15:30:00'));

        // Intento 1: Hora en el pasado (14:00 cuando son las 15:30)
        $resPast = $this->postJson('/api/reservations', [
            'nombre'   => 'Cliente Pasado',
            'telefono' => '7443334455',
            'fecha'    => '2026-09-14',
            'hora'     => '14:00',
            'personas' => 2,
        ]);

        $resPast->assertStatus(422)
                ->assertJsonValidationErrors(['hora']);

        $this->assertStringContainsString(
            'La hora seleccionada ya ha pasado',
            $resPast->json('errors.hora.0')
        );

        // Intento 2: Hora futura en el mismo día (17:00 cuando son las 15:30)
        $resFuture = $this->postJson('/api/reservations', [
            'nombre'   => 'Cliente Futuro',
            'telefono' => '7443334455',
            'fecha'    => '2026-09-14',
            'hora'     => '17:00',
            'personas' => 2,
        ]);

        $resFuture->assertStatus(201);

        Carbon::setTestNow(); // Restaurar tiempo real
    }

    /**
     * Test 8: Bloqueo de hora fuera de horario (antes de apertura y después de cierre).
     */
    public function test_zero_trust_blocks_out_of_hours_reservations(): void
    {
        RestaurantSetting::create([
            'schedule' => [
                ['day' => 'Miércoles', 'active' => 1, 'is_active' => 1, 'open' => '09:00', 'close' => '23:00'],
            ],
        ]);

        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY)->toDateString();

        // 1. Antes de apertura (08:30 < 09:00)
        $resBefore = $this->postJson('/api/reservations', [
            'nombre'   => 'Cliente Madrugador',
            'telefono' => '7445556677',
            'fecha'    => $wednesday,
            'hora'     => '08:30',
            'personas' => 2,
        ]);

        $resBefore->assertStatus(422)
                  ->assertJsonValidationErrors(['hora']);

        $this->assertStringContainsString(
            'fuera del horario de atención',
            $resBefore->json('errors.hora.0')
        );

        // 2. Después de cierre (23:30 > 23:00)
        $resAfter = $this->postJson('/api/reservations', [
            'nombre'   => 'Cliente Nocturno',
            'telefono' => '7445556677',
            'fecha'    => $wednesday,
            'hora'     => '23:30',
            'personas' => 2,
        ]);

        $resAfter->assertStatus(422)
                 ->assertJsonValidationErrors(['hora']);

        $this->assertStringContainsString(
            'fuera del horario de atención',
            $resAfter->json('errors.hora.0')
        );

        // 3. Dentro del rango (20:00 entre 09:00 y 23:00)
        $resValid = $this->postJson('/api/reservations', [
            'nombre'   => 'Cliente A Tiempo',
            'telefono' => '7445556677',
            'fecha'    => $wednesday,
            'hora'     => '20:00',
            'personas' => 2,
        ]);

        $resValid->assertStatus(201);
    }
}
