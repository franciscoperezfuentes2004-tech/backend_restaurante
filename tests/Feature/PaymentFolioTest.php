<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PaymentController;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentFolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-17 14:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test 1: El helper genera el folio con formato PAGYYYYMMDD0063 sin guiones.
     */
    public function test_generador_folio_pago_crea_formato_correcto_sin_guiones(): void
    {
        $order = new Order();
        $order->id = 63;
        $order->created_at = Carbon::parse('2026-09-17 12:30:00');

        $folio = PaymentController::generarFolioPago($order);

        $this->assertEquals('PAG202609170063', $folio);
        $this->assertStringNotContainsString('-', $folio);
        $this->assertMatchesRegularExpression('/^PAG\d{12}$/', $folio);
    }

    /**
     * Test 2: El endpoint GET /api/admin/payments devuelve los pagos con el nuevo folio PAG.
     */
    public function test_endpoint_admin_payments_lista_folios_compactos(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $order = Order::create([
            'folio'            => 'DEL202609170001',
            'modality'         => 'delivery',
            'status'           => 'completed',
            'payment_status'   => 'paid',
            'payment_method'   => 'cash',
            'total_amount'     => 350.50,
            'customer_name'    => 'Carlos Mendoza',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Av Costera 123',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/payments');

        $response->assertStatus(200)
            ->assertJsonStructure(['pagos', 'resumen']);

        $pagos = $response->json('pagos.data') ?? $response->json('pagos');
        $this->assertNotEmpty($pagos);
        
        $primerPago = $pagos[0];
        $expectedFolio = 'PAG20260917' . str_pad($order->id, 4, '0', STR_PAD_LEFT);
        $this->assertEquals($expectedFolio, $primerPago['folio']);
        $this->assertStringNotContainsString('-', $primerPago['folio']);
    }

    /**
     * Test 3: El endpoint GET /api/admin/payments/{id} devuelve el detalle con el nuevo folio y resuelve por PAG...
     */
    public function test_endpoint_admin_payments_show_devuelve_folio_y_resuelve_por_codigo_pag(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $order = Order::create([
            'folio'            => 'DEL202609170010',
            'modality'         => 'delivery',
            'status'           => 'completed',
            'payment_status'   => 'paid',
            'payment_method'   => 'terminal',
            'total_amount'     => 450.00,
            'customer_name'    => 'Laura Sánchez',
            'customer_phone'   => '7442223344',
            'customer_address' => 'Calle Palma 45',
        ]);

        $expectedFolio = 'PAG20260917' . str_pad($order->id, 4, '0', STR_PAD_LEFT);

        // Consulta por ID numérico
        $resById = $this->actingAs($admin)->getJson("/api/admin/payments/{$order->id}");
        $resById->assertStatus(200);
        $this->assertEquals($expectedFolio, $resById->json('folio'));

        // Consulta por Folio PAG
        $resByPag = $this->actingAs($admin)->getJson("/api/admin/payments/{$expectedFolio}");
        $resByPag->assertStatus(200);
        $this->assertEquals($expectedFolio, $resByPag->json('folio'));
    }

    /**
     * Test 4: Búsqueda de pagos por código de folio PAG.
     */
    public function test_busqueda_pagos_por_folio_pag(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $order1 = Order::create([
            'folio'            => 'DEL202609170001',
            'modality'         => 'delivery',
            'status'           => 'completed',
            'payment_status'   => 'paid',
            'payment_method'   => 'cash',
            'total_amount'     => 100.00,
            'customer_name'    => 'Juan Pérez',
            'customer_phone'   => '7443334455',
            'customer_address' => 'Col Centro 10',
        ]);

        $order2 = Order::create([
            'folio'            => 'DEL202609170002',
            'modality'         => 'delivery',
            'status'           => 'completed',
            'payment_status'   => 'paid',
            'payment_method'   => 'transfer',
            'total_amount'     => 200.00,
            'customer_name'    => 'María López',
            'customer_phone'   => '7445556677',
            'customer_address' => 'Col Progreso 20',
        ]);

        $targetFolio = 'PAG20260917' . str_pad($order2->id, 4, '0', STR_PAD_LEFT);

        $response = $this->actingAs($admin)->getJson("/api/admin/payments?search={$targetFolio}");
        $response->assertStatus(200);

        $pagos = $response->json('pagos.data') ?? $response->json('pagos');
        $this->assertCount(1, $pagos);
        $this->assertEquals($targetFolio, $pagos[0]['folio']);
        $this->assertEquals('María López', $pagos[0]['customer_name']);
    }
}
