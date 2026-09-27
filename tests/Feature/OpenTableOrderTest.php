<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Area;
use App\Models\Mesa;
use App\Models\Table;
use App\Models\Order;
use App\Models\Dish;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;

class OpenTableOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $mesero1;
    protected User $mesero2;
    protected User $admin;
    protected Area $area;
    protected Mesa $mesa;
    protected Dish $dish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mesero1 = User::factory()->create([
            'role' => 'mesero',
            'name' => 'Juan Mesero'
        ]);

        $this->mesero2 = User::factory()->create([
            'role' => 'mesero',
            'name' => 'Pedro Mesero'
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Gerente Admin'
        ]);

        $this->area = Area::create([
            'nombre'             => 'Terraza',
            'name'               => 'Terraza',
            'capacidad_personas' => 40,
            'capacity'           => 40,
            'numero_mesas'       => 10,
            'tables_count'       => 10,
            'capacity_per_table' => 4,
            'is_active'          => true,
            'active'             => true,
        ]);

        $this->mesa = Mesa::create([
            'area_id'     => $this->area->id,
            'numero_mesa' => 5,
            'capacidad'   => 4,
            'is_active'   => true,
            'status'      => 'libre',
        ]);

        $category = Category::create([
            'name'        => 'Bebidas',
            'slug'        => 'bebidas',
            'description' => 'Bebidas refrescantes',
            'is_active'   => true,
        ]);

        $this->dish = Dish::create([
            'category_id' => $category->id,
            'name'        => 'Limonada Mineral',
            'slug'        => 'limonada-mineral',
            'price'       => 45.00,
            'is_active'   => true,
        ]);
    }

    public function test_validation_rejects_missing_or_invalid_table_id(): void
    {
        // 1. Sin table_id
        $resEmpty = $this->actingAs($this->mesero1)->postJson('/api/tables/open', []);
        $resEmpty->assertStatus(422);
        $resEmpty->assertJsonValidationErrors(['table_id']);
        $this->assertEquals('La mesa es obligatoria.', $resEmpty->json('errors.table_id.0'));

        // 2. Con ID inexistente
        $resNonExistent = $this->actingAs($this->mesero1)->postJson('/api/tables/open', [
            'table_id' => 99999
        ]);
        $resNonExistent->assertStatus(422);
        $resNonExistent->assertJsonValidationErrors(['table_id']);
        $this->assertEquals('La mesa seleccionada no existe.', $resNonExistent->json('errors.table_id.0'));
    }

    public function test_waiter_can_open_free_table_successfully(): void
    {
        $res = $this->actingAs($this->mesero1)->postJson('/api/tables/open', [
            'table_id'      => $this->mesa->id,
            'customer_name' => 'Familia Gómez',
            'notes'         => 'Mesa cerca del jardín <b>VIP</b>',
            'items'         => [
                [
                    'dish_id'  => $this->dish->id,
                    'quantity' => 2,
                    'notes'    => 'Sin azúcar'
                ]
            ]
        ]);

        $res->assertStatus(201);
        $res->assertJson([
            'message' => "Mesa #{$this->mesa->numero_mesa} abierta correctamente.",
            'table'   => [
                'id'     => $this->mesa->id,
                'status' => 'ocupada',
            ],
            'order'   => [
                'table_id'     => $this->mesa->id,
                'table_number' => '5',
                'status'       => 'pending',
                'modality'     => 'local',
                'total_amount' => 90.00,
            ]
        ]);

        // Verificar persistencia en base de datos
        $this->assertEquals('ocupada', $this->mesa->fresh()->status);
        $this->assertDatabaseHas('orders', [
            'table_id'      => $this->mesa->id,
            'table_number'  => '5',
            'status'        => 'pending',
            'customer_name' => 'Familia Gómez',
            'notes'         => 'Mesa cerca del jardín VIP',
            'waiter_id'     => $this->mesero1->id,
        ]);
    }

    public function test_race_condition_second_waiter_attempt_fails_with_409_conflict(): void
    {
        // 1. Mesero 1 abre la mesa
        $res1 = $this->actingAs($this->mesero1)->postJson('/api/tables/open', [
            'table_id' => $this->mesa->id,
        ]);
        $res1->assertStatus(201);
        $this->assertEquals('ocupada', $this->mesa->fresh()->status);

        // 2. Mesero 2 intenta abrir la misma mesa en el mismo instante -> 409 Conflict
        $res2 = $this->actingAs($this->mesero2)->postJson('/api/tables/open', [
            'table_id' => $this->mesa->id,
        ]);

        $res2->assertStatus(409);
        $res2->assertJson([
            'message' => 'Esta mesa ya fue ocupada por otro mesero.'
        ]);
    }

    public function test_waiter_id_is_strictly_assigned_from_auth_user(): void
    {
        // El frontend o un cliente malintencionado intenta suplantar el waiter_id
        $res = $this->actingAs($this->mesero1)->postJson('/api/tables/open', [
            'table_id'  => $this->mesa->id,
            'waiter_id' => 9999,
            'user_id'   => 8888,
        ]);

        $res->assertStatus(201);

        $order = Order::where('table_id', $this->mesa->id)->first();
        $this->assertNotNull($order);
        // Debe ser exactamente el ID del mesero autenticado
        $this->assertEquals($this->mesero1->id, $order->waiter_id);
        $this->assertEquals($this->mesero1->id, $order->user_id);
    }

    public function test_database_partial_unique_index_prevents_duplicate_active_orders(): void
    {
        // Crear la primera orden activa para la mesa
        Order::create([
            'folio'            => 'ORD-MESA-1',
            'dispatch_token'   => 'TK-M1',
            'table_id'         => $this->mesa->id,
            'table_number'     => (string) $this->mesa->numero_mesa,
            'customer_name'    => 'Mesa 5 - Cuenta 1',
            'customer_phone'   => '',
            'customer_address' => 'Mesa 5',
            'modality'         => 'local',
            'status'           => 'pending',
            'total_amount'     => 100.00,
        ]);

        // Intentar crear directamente una segunda orden activa en la misma mesa debe violar el índice único
        $this->expectException(QueryException::class);

        Order::create([
            'folio'            => 'ORD-MESA-2',
            'dispatch_token'   => 'TK-M2',
            'table_id'         => $this->mesa->id,
            'table_number'     => (string) $this->mesa->numero_mesa,
            'customer_name'    => 'Mesa 5 - Cuenta 2 Duplicada',
            'customer_phone'   => '',
            'customer_address' => 'Mesa 5',
            'modality'         => 'local',
            'status'           => 'pending',
            'total_amount'     => 150.00,
        ]);
    }

    public function test_table_status_returns_to_libre_when_order_is_completed(): void
    {
        // 1. Abrir mesa
        $resOpen = $this->actingAs($this->mesero1)->postJson('/api/tables/open', [
            'table_id' => $this->mesa->id,
        ]);
        $resOpen->assertStatus(201);
        $orderId = $resOpen->json('order.id');

        $this->assertEquals('ocupada', $this->mesa->fresh()->status);

        // 2. Pasar la comanda a en_preparacion y luego a listo/completed
        $this->actingAs($this->admin)->postJson('/api/orders/status', [
            'order_id' => $orderId,
            'status'   => 'en_preparacion'
        ])->assertStatus(200);

        $resListo = $this->actingAs($this->admin)->postJson('/api/orders/status', [
            'order_id' => $orderId,
            'status'   => 'listo'
        ]);
        $resListo->assertStatus(200);

        // Al completarse la orden local, la mesa debe retornar a 'libre'
        $this->assertEquals('libre', $this->mesa->fresh()->status);

        // 3. Mesero 2 ahora puede abrir la mesa sin conflicto
        $resOpenAgain = $this->actingAs($this->mesero2)->postJson('/api/tables/open', [
            'table_id' => $this->mesa->id,
        ]);
        $resOpenAgain->assertStatus(201);
        $this->assertEquals('ocupada', $this->mesa->fresh()->status);
    }
}
