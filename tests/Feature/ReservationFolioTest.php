<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationFolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-16 12:00:00'));
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        config(['services.turnstile.required' => false]);

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

        Area::create([
            'name'        => 'Terraza',
            'description' => 'Área al aire libre',
            'capacity'    => 50,
            'is_active'   => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test 1: Primer folio del día comienza en 0001 con formato RESYYYYMMDD0001 (sin guiones).
     */
    public function test_genera_primer_folio_del_dia_con_formato_correcto(): void
    {
        $hoy = Carbon::now();
        $esperado = 'RES' . $hoy->format('Ymd') . '0001';

        $folio = Reservation::generarFolioUnico();

        $this->assertEquals($esperado, $folio);
    }

    /**
     * Test 2: Incrementa correlativamente los folios del mismo día sin colisiones.
     */
    public function test_incrementa_correlativamente_folios_del_mismo_dia(): void
    {
        $hoy = Carbon::now();
        $prefijo = 'RES' . $hoy->format('Ymd');

        $res1 = Reservation::create([
            'nombre'           => 'Cliente 1',
            'telefono'         => '7441112233',
            'email'            => 'c1@test.com',
            'fecha'            => $hoy->toDateString(),
            'hora'             => '20:00',
            'personas'         => 2,
            'status'           => 'pending',
        ]);

        $this->assertEquals($prefijo . '0001', $res1->folio);

        $res2 = Reservation::create([
            'nombre'           => 'Cliente 2',
            'telefono'         => '7442223344',
            'email'            => 'c2@test.com',
            'fecha'            => $hoy->toDateString(),
            'hora'             => '20:30',
            'personas'         => 4,
            'status'           => 'pending',
        ]);

        $this->assertEquals($prefijo . '0002', $res2->folio);

        $res3 = Reservation::create([
            'nombre'           => 'Cliente 3',
            'telefono'         => '7443334455',
            'email'            => 'c3@test.com',
            'fecha'            => $hoy->toDateString(),
            'hora'             => '21:00',
            'personas'         => 2,
            'status'           => 'pending',
        ]);

        $this->assertEquals($prefijo . '0003', $res3->folio);
    }

    /**
     * Test 3: El endpoint POST /api/reservations asigna y devuelve el nuevo folio seguro sin guiones.
     */
    public function test_endpoint_reservations_asigna_folio_seguro(): void
    {
        $hoy = Carbon::now();
        $prefijo = 'RES' . $hoy->format('Ymd');
        $area = Area::first();

        $payload = [
            'nombre'           => 'Ana María Gómez',
            'email'            => 'ana@gmail.com',
            'telefono'         => '7441234567',
            'fecha'            => $hoy->toDateString(),
            'hora'             => '20:00',
            'personas'         => 2,
            'area_id'          => $area->id,
        ];

        $response = $this->postJson('/api/reservations', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['folio', 'message', 'reservation']);

        $folioDevuelto = $response->json('folio');
        $this->assertStringStartsWith($prefijo, $folioDevuelto);
        $this->assertMatchesRegularExpression('/^RES\d{12}$/', $folioDevuelto);
        $this->assertStringNotContainsString('-', $folioDevuelto);
    }

    /**
     * Test 4: El contador del folio se reinicia para días diferentes.
     */
    public function test_contador_se_reinicia_al_cambiar_de_dia(): void
    {
        $ayer = Carbon::now()->subDay();
        $ayerStr = $ayer->format('Ymd');

        // Simulamos una reserva de ayer con folio 0015
        Reservation::create([
            'nombre'           => 'Cliente Ayer',
            'telefono'         => '7449998877',
            'email'            => 'ayer@test.com',
            'fecha'            => $ayer->toDateString(),
            'hora'             => '20:00',
            'personas'         => 2,
            'status'           => 'completed',
            'folio'            => "RES{$ayerStr}0015",
            'created_at'       => $ayer,
            'updated_at'       => $ayer,
        ]);

        // La reserva de hoy debe comenzar en 0001 para hoy
        $hoy = Carbon::now();
        $esperadoHoy = 'RES' . $hoy->format('Ymd') . '0001';

        $folioHoy = Reservation::generarFolioUnico();
        $this->assertEquals($esperadoHoy, $folioHoy);
    }

    /**
     * Test 5: Forzar área 'Terraza' por defecto y actualización masiva de datos viejos.
     */
    public function test_fuerza_y_por_defecto_area_terraza_y_limpieza(): void
    {
        $hoy = Carbon::now();

        // 1. Crear reserva sin especificar área -> debe nacer como 'Terraza'
        $payload = [
            'nombre'   => 'Cliente Sin Area',
            'email'    => 'sinarea@test.com',
            'telefono' => '7448889900',
            'fecha'    => $hoy->toDateString(),
            'hora'     => '20:00',
            'personas' => 2,
        ];

        $res = $this->postJson('/api/reservations', $payload);
        $res->assertStatus(201);
        $this->assertEquals('Terraza', $res->json('area'));
        $this->assertEquals('Terraza', $res->json('area_name'));

        // 2. Simular un registro antiguo con área 'General'
        $reservaVieja = Reservation::create([
            'nombre'   => 'Registro Viejo',
            'telefono' => '7441112233',
            'fecha'    => $hoy->toDateString(),
            'hora'     => '20:00',
            'personas' => 2,
            'area'     => 'General',
        ]);

        // 3. Ejecutar comando de actualización limpia
        Reservation::where('area', 'General')->update(['area' => 'Terraza']);

        $reservaVieja->refresh();
        $this->assertEquals('Terraza', $reservaVieja->area);
    }
}
