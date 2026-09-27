<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\DeliveryDriver;
use App\Models\CashCut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class CloseDriverShiftTest extends TestCase
{
    use RefreshDatabase;

    protected User $repartidor;
    protected DeliveryDriver $driver;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repartidor = User::factory()->create([
            'role' => 'repartidor',
            'name' => 'Carlos Repartidor'
        ]);

        $this->driver = DeliveryDriver::create([
            'user_id'      => $this->repartidor->id,
            'name'         => 'Carlos Repartidor',
            'phone'        => '7441234567',
            'email'        => $this->repartidor->email,
            'status'       => 'active',
            'active'       => true,
            'vehicle_type' => 'motorcycle',
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Test'
        ]);
    }

    public function test_client_cash_payload_is_strictly_ignored_and_calculated_from_database(): void
    {
        // 1. Crear 2 órdenes entregadas: una en efectivo ($150) y otra con tarjeta ($200)
        $order1 = Order::create([
            'folio'            => 'ORD-CASH-1',
            'dispatch_token'   => 'TK-CASH-1',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'payment_method'   => 'cash',
            'total_amount'     => 150.00,
            'customer_name'    => 'Cliente 1',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Av Costera 1',
        ]);
        Delivery::create([
            'order_id'  => $order1->id,
            'driver_id' => $this->driver->id,
            'status'    => 'delivered',
        ]);

        $order2 = Order::create([
            'folio'            => 'ORD-CARD-1',
            'dispatch_token'   => 'TK-CARD-1',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'payment_method'   => 'card',
            'total_amount'     => 200.00,
            'customer_name'    => 'Cliente 2',
            'customer_phone'   => '7442223344',
            'customer_address' => 'Av Costera 2',
        ]);
        Delivery::create([
            'order_id'  => $order2->id,
            'driver_id' => $this->driver->id,
            'status'    => 'delivered',
        ]);

        // 2. Cliente envía manipulación de monto: {"cash_declared": 0, "efectivo_a_entregar": 0}
        $res = $this->actingAs($this->repartidor)->postJson('/api/driver/corte/notificar', [
            'cash_declared'        => 0,
            'efectivo_a_entregar'  => 0,
            'monto_declarado'      => 0,
            'notes'                => 'Turno finalizado <b>sin novedad</b>',
        ]);

        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'data'    => [
                'expected_cash'  => 150.00,
                'cash_declared'  => 150.00,
                'total_cash'     => 150.00,
                'total_card'     => 200.00,
                'total_amount'   => 350.00,
                'total_orders'   => 2,
                'status'         => 'pendiente',
            ]
        ]);

        // 3. Verificar que en la base de datos se guardó el cálculo estricto del servidor y notas sanitizadas
        $this->assertDatabaseHas('cash_cuts', [
            'user_id'       => $this->repartidor->id,
            'cash_declared' => 150.00,
            'expected_cash' => 150.00,
            'total_orders'  => 2,
            'status'        => 'pendiente',
            'notes'         => 'Turno finalizado sin novedad',
        ]);
    }

    public function test_idempotency_duplicate_or_second_close_request_returns_409_conflict(): void
    {
        // Crear un corte de caja pendiente existente para el repartidor
        CashCut::create([
            'user_id'       => $this->repartidor->id,
            'cut_type'      => 'repartidor',
            'cash_declared' => 300.00,
            'expected_cash' => 300.00,
            'total_orders'  => 3,
            'status'        => 'pendiente',
        ]);

        // Intentar cerrar de nuevo el turno
        $res = $this->actingAs($this->repartidor)->postJson('/api/driver/corte/notificar', [
            'notes' => 'Intento duplicado'
        ]);

        $res->assertStatus(409);
        $res->assertJson([
            'message' => 'Tu corte ya ha sido notificado y cerrado.'
        ]);
    }

    public function test_post_cut_lock_blocks_order_assignment(): void
    {
        // 1. Repartidor con corte en estado pendiente
        CashCut::create([
            'user_id'       => $this->repartidor->id,
            'cut_type'      => 'repartidor',
            'cash_declared' => 150.00,
            'expected_cash' => 150.00,
            'total_orders'  => 1,
            'status'        => 'pendiente',
        ]);

        // 2. Orden lista en cocina
        $order = Order::create([
            'folio'            => 'ORD-RDY-LOCK',
            'dispatch_token'   => 'TK-RDY-LOCK',
            'modality'         => 'delivery',
            'status'           => 'ready',
            'total_amount'     => 120.00,
            'customer_name'    => 'Cliente Bloqueo',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Calle 5',
        ]);

        // 3. El repartidor intenta autoasignarse el pedido mientras su corte está pendiente
        $res = $this->actingAs($this->repartidor)->postJson('/api/driver/orders/assign', [
            'token' => 'TK-RDY-LOCK'
        ]);

        $res->assertStatus(409);
        $res->assertJson([
            'message' => 'No puedes tomar nuevos pedidos porque tu turno está cerrado/notificado. Espera a que el encargado confirme tu corte.'
        ]);

        // El pedido sigue en estado 'ready'
        $this->assertEquals('ready', $order->fresh()->status);
    }

    public function test_admin_confirmation_unlocks_driver_assignment(): void
    {
        // 1. Repartidor con corte pendiente
        $cashCut = CashCut::create([
            'user_id'       => $this->repartidor->id,
            'cut_type'      => 'repartidor',
            'cash_declared' => 150.00,
            'expected_cash' => 150.00,
            'total_orders'  => 1,
            'status'        => 'pendiente',
        ]);

        $order = Order::create([
            'folio'            => 'ORD-RDY-UNLOCK',
            'dispatch_token'   => 'TK-RDY-UNLOCK',
            'modality'         => 'delivery',
            'status'           => 'ready',
            'total_amount'     => 120.00,
            'customer_name'    => 'Cliente Desbloqueado',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Calle 10',
        ]);

        // 2. Admin confirma el corte
        $confirmRes = $this->actingAs($this->admin)->postJson("/api/admin/cash-cuts/{$cashCut->id}/confirmar", [
            'received_cash' => 150.00,
            'notes'         => 'Todo en orden'
        ]);
        $confirmRes->assertStatus(200);

        $this->assertEquals('confirmado', $cashCut->fresh()->status);

        // 3. Ahora el repartidor intenta asignarse el pedido -> Éxito 200 OK
        $assignRes = $this->actingAs($this->repartidor)->postJson('/api/driver/orders/assign', [
            'token' => 'TK-RDY-UNLOCK'
        ]);

        $assignRes->assertStatus(200);
        $assignRes->assertJson([
            'status' => 'on_the_way',
            'estado' => 'en_camino'
        ]);

        $this->assertEquals('on_the_way', $order->fresh()->status);
    }
}
