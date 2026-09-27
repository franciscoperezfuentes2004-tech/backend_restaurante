<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\DeliveryDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AssignDeliveryOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $repartidor1;
    protected User $repartidor2;
    protected User $admin;
    protected User $cajero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repartidor1 = User::factory()->create(['role' => 'repartidor']);
        $this->repartidor2 = User::factory()->create(['role' => 'repartidor']);
        $this->admin       = User::factory()->create(['role' => 'admin']);
        $this->cajero      = User::factory()->create(['role' => 'cajero']);
    }

    public function test_non_repartidor_cannot_assign_delivery_order(): void
    {
        $order = Order::create([
            'folio'            => 'TST-RDY-1',
            'dispatch_token'   => 'TK-RDY-1',
            'modality'         => 'delivery',
            'status'           => 'ready',
            'total_amount'     => 100,
            'customer_name'    => 'Cliente Test',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Dirección 123',
        ]);

        $resAdmin = $this->actingAs($this->admin)->postJson('/api/driver/orders/assign', [
            'token' => 'TK-RDY-1'
        ]);
        $resAdmin->assertStatus(403);

        $resCajero = $this->actingAs($this->cajero)->postJson('/api/driver/orders/assign', [
            'token' => 'TK-RDY-1'
        ]);
        $resCajero->assertStatus(403);
    }

    public function test_filter_1_rejects_non_delivery_orders(): void
    {
        $order = Order::create([
            'folio'            => 'TST-PKP-1',
            'dispatch_token'   => 'TK-PKP-1',
            'modality'         => 'pickup',
            'status'           => 'ready',
            'total_amount'     => 100,
            'customer_name'    => 'Cliente Test',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Sucursal',
        ]);

        $res = $this->actingAs($this->repartidor1)->postJson('/api/driver/orders/assign', [
            'token' => 'TK-PKP-1'
        ]);

        $res->assertStatus(400);
        $res->assertJson([
            'message' => 'Este código pertenece a un pedido que no es para envío a domicilio.'
        ]);
    }

    public function test_filter_2_rejects_orders_not_ready_in_kitchen(): void
    {
        $orderPending = Order::create([
            'folio'            => 'TST-PND-1',
            'dispatch_token'   => 'TK-PND-1',
            'modality'         => 'delivery',
            'status'           => 'pending',
            'total_amount'     => 100,
            'customer_name'    => 'Cliente Test',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Dirección 123',
        ]);

        $resPending = $this->actingAs($this->repartidor1)->postJson('/api/driver/orders/assign', [
            'token' => 'TK-PND-1'
        ]);
        $resPending->assertStatus(400);
        $resPending->assertJson([
            'message' => 'El pedido aún no está listo en cocina.'
        ]);

        $orderPreparing = Order::create([
            'folio'            => 'TST-PRP-1',
            'dispatch_token'   => 'TK-PRP-1',
            'modality'         => 'delivery',
            'status'           => 'preparing',
            'total_amount'     => 100,
            'customer_name'    => 'Cliente Test',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Dirección 123',
        ]);

        $resPreparing = $this->actingAs($this->repartidor1)->postJson('/api/driver/orders/assign', [
            'token' => 'TK-PRP-1'
        ]);
        $resPreparing->assertStatus(400);
        $resPreparing->assertJson([
            'message' => 'El pedido aún no está listo en cocina.'
        ]);
    }

    public function test_filter_3_prevents_duplicate_assignment_with_409_conflict(): void
    {
        $order = Order::create([
            'folio'            => 'TST-RDY-2',
            'dispatch_token'   => 'TK-RDY-2',
            'modality'         => 'delivery',
            'status'           => 'ready',
            'total_amount'     => 250,
            'customer_name'    => 'Cliente Test',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Dirección 123',
        ]);

        // Repartidor 1 asigna con éxito
        $res1 = $this->actingAs($this->repartidor1)->postJson('/api/driver/orders/assign', [
            'token' => '<b>tk-rdy-2</b>'
        ]);
        $res1->assertStatus(200);
        $res1->assertJson([
            'status' => 'on_the_way',
            'estado' => 'en_camino',
        ]);

        $this->assertEquals('on_the_way', $order->fresh()->status);

        // Repartidor 2 intenta asignar la misma orden -> 409 Conflict
        $res2 = $this->actingAs($this->repartidor2)->postJson('/api/driver/orders/assign', [
            'token' => 'TK-RDY-2'
        ]);
        $res2->assertStatus(409);
        $res2->assertJson([
            'message' => 'Este pedido ya fue tomado por otro repartidor.'
        ]);
    }
}
