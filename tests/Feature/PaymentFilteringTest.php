<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-17 15:00:00'));
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test 1: Filtrado combinado con when() y cálculo dinámico de métricas (total_registros y suma_total).
     */
    public function test_motor_de_filtrado_calcula_metricas_y_suma_total_en_vuelo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Pago 1: Hoy, Efectivo, Pagado ($1,500.00)
        $o1 = Order::create([
            'folio'            => 'DEL202609170001',
            'modality'         => 'delivery',
            'status'           => 'completed',
            'payment_status'   => 'paid',
            'payment_method'   => 'cash',
            'total_amount'     => 1500.00,
            'customer_name'    => 'Carlos Slim',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Av Costera 100',
        ]);
        $o1->created_at = Carbon::parse('2026-09-17 10:00:00');
        $o1->saveQuietly();

        // Pago 2: Hoy, Terminal, Pagado ($2,320.00)
        $o2 = Order::create([
            'folio'            => 'DEL202609170002',
            'modality'         => 'delivery',
            'status'           => 'completed',
            'payment_status'   => 'paid',
            'payment_method'   => 'terminal',
            'total_amount'     => 2320.00,
            'customer_name'    => 'Ana Karenina',
            'customer_phone'   => '7442223344',
            'customer_address' => 'Calle Palma 20',
        ]);
        $o2->created_at = Carbon::parse('2026-09-17 11:30:00');
        $o2->saveQuietly();

        // Pago 3: Hace 10 días (dentro de 30dias), Transferencia, Pagado ($2,500.00)
        $o3 = Order::create([
            'folio'            => 'DEL202609070003',
            'modality'         => 'delivery',
            'status'           => 'completed',
            'payment_status'   => 'paid',
            'payment_method'   => 'transfer',
            'total_amount'     => 2500.00,
            'customer_name'    => 'Roberto Gómez',
            'customer_phone'   => '7443334455',
            'customer_address' => 'Av Cuauhtémoc 30',
        ]);
        $o3->created_at = Carbon::parse('2026-09-07 12:00:00');
        $o3->saveQuietly();

        // Pago 4: Hace 10 días, Cancelado ($800.00 - no debe sumarse a suma_total)
        $o4 = Order::create([
            'folio'            => 'DEL202609070004',
            'modality'         => 'delivery',
            'status'           => 'cancelled',
            'payment_status'   => 'cancelled',
            'payment_method'   => 'cash',
            'total_amount'     => 800.00,
            'customer_name'    => 'Cancelado Test',
            'customer_phone'   => '7444445566',
            'customer_address' => 'Col Centro 40',
        ]);
        $o4->created_at = Carbon::parse('2026-09-07 14:00:00');
        $o4->saveQuietly();

        // Pago 5: Hace 45 días (dentro de 60dias) ($1,000.00)
        $o5 = Order::create([
            'folio'            => 'DEL202608030005',
            'modality'         => 'delivery',
            'status'           => 'completed',
            'payment_status'   => 'paid',
            'payment_method'   => 'cash',
            'total_amount'     => 1000.00,
            'customer_name'    => 'Antiguo 45 días',
            'customer_phone'   => '7445556677',
            'customer_address' => 'Col Zapata 50',
        ]);
        $o5->created_at = Carbon::parse('2026-08-03 12:00:00');
        $o5->saveQuietly();

        // ── Consulta 1: Filtro "30dias" ───────────────────────────────────────
        // Esperados dentro de 30 días: Pagos 1, 2, 3, 4 (Total registros: 4)
        // Suma total sin cancelados: 1500 + 2320 + 2500 = $6,320.00 (exacto del requerimiento)
        $res30 = $this->actingAs($admin)->getJson('/api/admin/payments?tiempo=30dias');
        $res30->assertStatus(200);
        $res30->assertJsonPath('metricas.total_registros', 4);
        $res30->assertJsonPath('metricas.suma_total', 6320);

        // ── Consulta 2: Filtro "hoy" ──────────────────────────────────────────
        // Esperados hoy: Pagos 1, 2 (Total registros: 2, Suma: 1500 + 2320 = 3820)
        $resHoy = $this->actingAs($admin)->getJson('/api/admin/payments?tiempo=hoy');
        $resHoy->assertStatus(200);
        $resHoy->assertJsonPath('metricas.total_registros', 2);
        $resHoy->assertJsonPath('metricas.suma_total', 3820);

        // ── Consulta 3: Filtro por búsqueda de cliente ────────────────────────
        $resSearch = $this->actingAs($admin)->getJson('/api/admin/payments?busqueda=Slim');
        $resSearch->assertStatus(200);
        $resSearch->assertJsonPath('metricas.total_registros', 1);
        $resSearch->assertJsonPath('metricas.suma_total', 1500);

        // ── Consulta 4: Filtro por método de pago ─────────────────────────────
        $resMetodo = $this->actingAs($admin)->getJson('/api/admin/payments?tiempo=30dias&metodo=terminal');
        $resMetodo->assertStatus(200);
        $resMetodo->assertJsonPath('metricas.total_registros', 1);
        $resMetodo->assertJsonPath('metricas.suma_total', 2320);
    }
}