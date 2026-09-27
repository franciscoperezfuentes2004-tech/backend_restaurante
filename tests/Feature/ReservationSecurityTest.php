<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Reservation;
use App\Events\NewReservationReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReservationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
    }

    /**
     * Test 1: 'nombre' bloquea símbolos e inyección HTML (<script>, @#$).
     */
    public function test_nombre_rejects_symbols_and_html_injection(): void
    {
        $payloadHtml = [
            'nombre'   => '<script>alert(1)</script>',
            'telefono' => '7441234567',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '14:00',
            'personas' => 2,
        ];

        $resHtml = $this->postJson('/api/reservations', $payloadHtml);
        $resHtml->assertStatus(422)
                ->assertJsonValidationErrors(['nombre']);

        $payloadSymbols = [
            'nombre'   => 'Juan @#$ 123',
            'telefono' => '7441234567',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '14:00',
            'personas' => 2,
        ];

        $resSymbols = $this->postJson('/api/reservations', $payloadSymbols);
        $resSymbols->assertStatus(422)
                   ->assertJsonValidationErrors(['nombre']);
    }

    /**
     * Test 2: 'nombre' acepta caracteres alfabéticos y espacios.
     */
    public function test_nombre_accepts_letters_and_spaces(): void
    {
        $payload = [
            'nombre'   => 'Juan Carlos Perez',
            'telefono' => '7441234567',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '14:00',
            'personas' => 2,
        ];

        $res = $this->postJson('/api/reservations', $payload);
        $res->assertStatus(201)
            ->assertJsonPath('nombre', 'Juan Carlos Perez');
    }

    /**
     * Test 3: 'telefono' exige exactamente 10 dígitos numéricos (digits:10).
     */
    public function test_telefono_strictly_requires_exact_10_digits(): void
    {
        // 8 dígitos
        $this->postJson('/api/reservations', [
            'nombre'   => 'Carlos Santana',
            'telefono' => '74412345',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '14:00',
            'personas' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors(['telefono']);

        // 12 dígitos
        $this->postJson('/api/reservations', [
            'nombre'   => 'Carlos Santana',
            'telefono' => '744123456789',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '14:00',
            'personas' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors(['telefono']);

        // 10 letras
        $this->postJson('/api/reservations', [
            'nombre'   => 'Carlos Santana',
            'telefono' => 'abcdefghij',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '14:00',
            'personas' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors(['telefono']);

        // 10 dígitos exactos -> Válido
        $this->postJson('/api/reservations', [
            'nombre'   => 'Carlos Santana',
            'telefono' => '7441234567',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '14:00',
            'personas' => 2,
        ])->assertStatus(201);
    }

    /**
     * Test 4: 'fecha' es obligatoria y rechaza fechas en el pasado (after_or_equal:today).
     */
    public function test_fecha_cannot_be_in_the_past(): void
    {
        // Ayer -> Rechazado
        $this->postJson('/api/reservations', [
            'nombre'   => 'Maria Lopez',
            'telefono' => '7449876543',
            'fecha'    => now()->subDay()->toDateString(),
            'hora'     => '15:00',
            'personas' => 4,
        ])->assertStatus(422)->assertJsonValidationErrors(['fecha']);

        // Hoy con hora futura -> Válido
        \Carbon\Carbon::setTestNow(now()->startOfDay()->setHour(12));
        $this->postJson('/api/reservations', [
            'nombre'   => 'Maria Lopez',
            'telefono' => '7449876543',
            'fecha'    => now()->toDateString(),
            'hora'     => '15:00',
            'personas' => 4,
        ])->assertStatus(201);
        \Carbon\Carbon::setTestNow();
    }

    /**
     * Test 5: 'hora' exige formato estricto H:i (date_format:H:i).
     */
    public function test_hora_strictly_requires_format_H_i(): void
    {
        // Formato inválido
        $this->postJson('/api/reservations', [
            'nombre'   => 'Pedro Gomez',
            'telefono' => '7441112233',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '25:99',
            'personas' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors(['hora']);

        // Formato válido
        $this->postJson('/api/reservations', [
            'nombre'   => 'Pedro Gomez',
            'telefono' => '7441112233',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '19:30',
            'personas' => 2,
        ])->assertStatus(201);
    }

    /**
     * Test 6: 'personas' exige un número entero entre 1 y 20 (min:1|max:20).
     */
    public function test_personas_strictly_bounded_between_1_and_20(): void
    {
        // 0 personas -> Rechazado
        $this->postJson('/api/reservations', [
            'nombre'   => 'Elena Ramos',
            'telefono' => '7445556677',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '18:00',
            'personas' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors(['personas']);

        // 21 personas -> Rechazado
        $this->postJson('/api/reservations', [
            'nombre'   => 'Elena Ramos',
            'telefono' => '7445556677',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '18:00',
            'personas' => 21,
        ])->assertStatus(422)->assertJsonValidationErrors(['personas']);

        // 20 personas -> Válido (límite superior exacto)
        $this->postJson('/api/reservations', [
            'nombre'   => 'Elena Ramos',
            'telefono' => '7445556677',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '18:00',
            'personas' => 20,
        ])->assertStatus(201);
    }

    /**
     * Test 7: 'nota_especial' limpia y sanitiza etiquetas <script> maliciosas.
     */
    public function test_nota_especial_strips_script_tags(): void
    {
        $res = $this->postJson('/api/reservations', [
            'nombre'        => 'Roberto Martinez',
            'telefono'      => '7443332211',
            'fecha'         => now()->addDay()->toDateString(),
            'hora'          => '21:00',
            'personas'      => 4,
            'nota_especial' => '<script>alert("xss")</script>Mesa en terraza con vista al mar',
        ]);

        $res->assertStatus(201);

        $savedReservation = Reservation::latest('id')->first();
        $this->assertNotNull($savedReservation);
        $this->assertStringNotContainsString('<script>', $savedReservation->nota_especial);
        $this->assertStringContainsString('Mesa en terraza con vista al mar', $savedReservation->nota_especial);
    }

    /**
     * Test 8: 'email' es nullable pero rechaza formatos de correo corruptos.
     */
    public function test_email_rejects_corrupted_format_and_allows_null(): void
    {
        // Email corrupto -> Rechazado
        $this->postJson('/api/reservations', [
            'nombre'   => 'Laura Diaz',
            'telefono' => '7444445566',
            'email'    => 'esto-no-es-un-email',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '20:00',
            'personas' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        // Email omitido/null -> Válido
        $this->postJson('/api/reservations', [
            'nombre'   => 'Laura Diaz',
            'telefono' => '7444445566',
            'email'    => null,
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '20:00',
            'personas' => 2,
        ])->assertStatus(201);
    }

    /**
     * Test 9: Persistencia en PostgreSQL con tipos de datos exactos y respuesta 201 con mensaje.
     */
    public function test_successful_reservation_persists_exact_postgresql_columns(): void
    {
        $dateStr = now()->addDays(2)->toDateString();

        $response = $this->postJson('/api/reservations', [
            'nombre'           => 'Alejandro Sanz',
            'telefono'         => '7447778899',
            'email'            => 'alejandro@sanz.com',
            'fecha'            => $dateStr,
            'hora'             => '20:30',
            'personas'         => 6,
            'zona_preferida'   => 'Terraza',
            'ocasion_especial' => 'Cumpleaños',
            'nota_especial'    => 'Decoración especial con velas',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('message', 'Reserva solicitada con éxito');

        // Verificación directa en base de datos PostgreSQL
        $dbRow = DB::table('reservations')->where('telefono', '7447778899')->first();

        $this->assertNotNull($dbRow);
        $this->assertEquals('Alejandro Sanz', $dbRow->nombre);
        $this->assertEquals('7447778899', $dbRow->telefono);
        $this->assertEquals('alejandro@sanz.com', $dbRow->email);
        $this->assertEquals($dateStr, $dbRow->fecha);
        $this->assertStringStartsWith('20:30', (string) $dbRow->hora);
        $this->assertEquals(6, $dbRow->personas);
        $this->assertEquals('Terraza', $dbRow->zona_preferida);
        $this->assertEquals('Cumpleaños', $dbRow->ocasion_especial);
        $this->assertEquals('Decoración especial con velas', $dbRow->nota_especial);
        $this->assertEquals('pendiente', $dbRow->estado);
    }

    /**
     * Test 10: Disparo sincrónico del evento WebSocket NewReservationReceived tras guardar.
     */
    public function test_dispatches_new_reservation_received_websocket_event(): void
    {
        Event::fake([NewReservationReceived::class]);

        $response = $this->postJson('/api/reservations', [
            'nombre'   => 'Valeria Castro',
            'telefono' => '7448889900',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '19:00',
            'personas' => 3,
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(NewReservationReceived::class, function ($event) {
            return $event->reservation->nombre === 'Valeria Castro'
                && $event->reservation->telefono === '7448889900';
        });
    }

    /**
     * Test 11: Estructura del evento NewReservationReceived (ShouldBroadcast, canales y payload).
     */
    public function test_new_reservation_received_event_broadcasting_properties(): void
    {
        $reservation = Reservation::create([
            'nombre'   => 'Sofia Reyes',
            'telefono' => '7440001122',
            'fecha'    => now()->addDay()->toDateString(),
            'hora'     => '21:00',
            'personas' => 2,
            'estado'   => 'pendiente',
        ]);

        $event = new NewReservationReceived($reservation);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);

        $channelNames = array_map(fn($ch) => $ch->name, $event->broadcastOn());
        $this->assertContains('reservations', $channelNames);
        $this->assertContains('admin.reservations', $channelNames);
        $this->assertContains('private-admin.notifications', $channelNames);

        $this->assertEquals('new_reservation_received', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('reservation', $payload);
        $this->assertArrayHasKey('timestamp', $payload);
        $this->assertEquals('Nueva reservación recibida', $payload['message']);
        $this->assertEquals('Sofia Reyes', $payload['reservation']->nombre);
    }
}