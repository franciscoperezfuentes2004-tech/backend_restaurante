<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Dish;
use App\Models\Extra;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class AddDishToOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $mesero;
    protected User $repartidor;
    protected Category $category;
    protected Dish $dishTacos;
    protected Dish $dishCorte;
    protected Extra $extraCebolla;
    protected Extra $extraChampagne;
    protected Order $activeOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mesero = User::factory()->create([
            'role' => 'mesero',
            'name' => 'Mesero Fraud Guard'
        ]);

        $this->repartidor = User::factory()->create([
            'role' => 'repartidor',
            'name' => 'Repartidor No Auth'
        ]);

        $this->category = Category::create([
            'name'        => 'Carnes y Tacos',
            'slug'        => 'carnes-y-tacos',
            'description' => 'Platillos principales',
            'active'      => true,
            'is_active'   => true,
        ]);

        // Platillo 1: Tacos de Cecina ($120)
        $this->dishTacos = Dish::create([
            'category_id'  => $this->category->id,
            'name'         => 'Tacos de Cecina',
            'slug'         => 'tacos-de-cecina',
            'price'        => 120.00,
            'is_active'    => true,
            'is_available' => true,
        ]);

        // Platillo 2: Corte Tomahawk ($1,200)
        $this->dishCorte = Dish::create([
            'category_id'  => $this->category->id,
            'name'         => 'Corte Tomahawk',
            'slug'         => 'corte-tomahawk',
            'price'        => 1200.00,
            'is_active'    => true,
            'is_available' => true,
        ]);

        // Extra 1: Cebolla Asada ($15) -> Autorizado SOLO para Tacos
        $this->extraCebolla = Extra::create([
            'name'    => 'Cebolla Asada Extra',
            'price'   => 15.00,
            'is_free' => false,
            'active'  => true,
        ]);

        // Extra 2: Botella Champagne ($2,500) -> Autorizado SOLO para Corte Tomahawk
        $this->extraChampagne = Extra::create([
            'name'    => 'Botella Moet Brut Imperial',
            'price'   => 2500.00,
            'is_free' => false,
            'active'  => true,
        ]);

        // Vincular relaciones en dish_extra
        DB::table('dish_extra')->insert([
            ['dish_id' => $this->dishTacos->id, 'extra_id' => $this->extraCebolla->id],
            ['dish_id' => $this->dishCorte->id, 'extra_id' => $this->extraChampagne->id],
        ]);

        // Orden activa inicial con total $0
        $this->activeOrder = Order::create([
            'folio'            => 'PED-TEST-1001',
            'daily_number'     => 1,
            'status'           => 'pending',
            'modality'         => 'local',
            'customer_name'    => 'Mesa 4',
            'customer_phone'   => '5551234567',
            'customer_address' => 'Mesa 4 Interior',
            'payment_method'   => 'cash',
            'total_amount'     => 0.00,
        ]);
    }

    public function test_zero_trust_client_prices_and_totals_are_strictly_ignored(): void
    {
        // El cliente malintencionado envía precio unitario $1.00 y total $2.00 para Tacos ($120)
        $payload = [
            'order_id'     => $this->activeOrder->id,
            'dish_id'      => $this->dishTacos->id,
            'quantity'     => 2,
            'price'        => 1.00,
            'subtotal'     => 2.00,
            'total'        => 2.00,
            'total_amount' => 2.00,
            'notes'        => 'Sin cebolla',
        ];

        $response = $this->actingAs($this->mesero)
            ->postJson("/api/orders/{$this->activeOrder->id}/items", $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('order_item.unit_price', 120);
        $response->assertJsonPath('order_item.subtotal', 240);
        $response->assertJsonPath('order.total_amount', 240);

        // Validar en la base de datos que se guardó el precio real de PostgreSQL
        $this->assertDatabaseHas('order_items', [
            'order_id' => $this->activeOrder->id,
            'dish_id'  => $this->dishTacos->id,
            'quantity' => 2,
            'price'    => 120.00,
        ]);

        // Validar que el total acumulado de la comanda en orders es $240.00 (NO $2.00)
        $this->assertDatabaseHas('orders', [
            'id'           => $this->activeOrder->id,
            'total_amount' => 240.00,
        ]);
    }

    public function test_foreign_extra_injection_is_rejected_with_422(): void
    {
        // Ataque: Inyectar la Botella de Champagne ($2,500) como extra de los Tacos de Cecina
        $payload = [
            'order_id' => $this->activeOrder->id,
            'dish_id'  => $this->dishTacos->id,
            'quantity' => 1,
            'extras'   => [$this->extraChampagne->id], // Extra de otro platillo
        ];

        $response = $this->actingAs($this->mesero)
            ->postJson("/api/orders/{$this->activeOrder->id}/items", $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['extras.0']);
        $this->assertEquals(
            'El extra seleccionado no pertenece a este platillo o no es válido.',
            $response->json('errors')['extras.0'][0]
        );

        // Comprobar que no se insertó ninguna partida fraudulenta en la base de datos
        $this->assertDatabaseMissing('order_items', [
            'order_id' => $this->activeOrder->id,
            'dish_id'  => $this->dishTacos->id,
        ]);
    }

    public function test_legitimate_extras_belonging_to_dish_are_accepted_and_calculated(): void
    {
        // Tacos ($120) + Cebolla Asada ($15) x 2 unidades = (120 + 15) * 2 = $270.00
        $payload = [
            'order_id' => $this->activeOrder->id,
            'dish_id'  => $this->dishTacos->id,
            'quantity' => 2,
            'extras'   => [$this->extraCebolla->id],
        ];

        $response = $this->actingAs($this->mesero)
            ->postJson("/api/orders/{$this->activeOrder->id}/items", $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('order_item.unit_price', 120);
        $response->assertJsonPath('order_item.subtotal', 270);
        $response->assertJsonPath('order.total_amount', 270);

        $this->assertDatabaseHas('order_item_extras', [
            'extra_id' => $this->extraCebolla->id,
            'price'    => 15.00,
        ]);

        $this->assertDatabaseHas('orders', [
            'id'           => $this->activeOrder->id,
            'total_amount' => 270.00,
        ]);
    }

    public function test_notes_are_strictly_sanitized_against_xss(): void
    {
        $payload = [
            'order_id' => $this->activeOrder->id,
            'dish_id'  => $this->dishTacos->id,
            'quantity' => 1,
            'notes'    => "<script>alert('XSS')</script><b>Salsa aparte</b> y bien caliente<img src=x onerror=alert(1)>",
        ];

        $response = $this->actingAs($this->mesero)
            ->postJson("/api/orders/{$this->activeOrder->id}/items", $payload);

        $response->assertStatus(201);

        $itemNotes = $response->json('order_item.notes');
        $this->assertStringNotContainsString('<script>', $itemNotes);
        $this->assertStringNotContainsString('</script>', $itemNotes);
        $this->assertStringNotContainsString('<b>', $itemNotes);
        $this->assertStringNotContainsString('<img', $itemNotes);
        $this->assertEquals('Salsa aparte y bien caliente', $itemNotes);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $this->activeOrder->id,
            'dish_id'  => $this->dishTacos->id,
            'notes'    => 'Salsa aparte y bien caliente',
        ]);
    }

    public function test_cannot_add_dish_to_completed_or_cancelled_order(): void
    {
        $this->activeOrder->update(['status' => 'completed']);

        $payload = [
            'order_id' => $this->activeOrder->id,
            'dish_id'  => $this->dishTacos->id,
            'quantity' => 1,
        ];

        $response = $this->actingAs($this->mesero)
            ->postJson("/api/orders/{$this->activeOrder->id}/items", $payload);

        $response->assertStatus(409);
        $this->assertEquals(
            'No se pueden agregar platillos a una comanda completada, entregada o cancelada.',
            $response->json('message')
        );
    }

    public function test_batch_order_creation_rejects_foreign_extras(): void
    {
        // En POST /api/orders (creación masiva), inyectar un extra ajeno debe abortar con 422
        $payload = [
            'modality'       => 'local',
            'payment_method' => 'cash',
            'customer_name'  => 'Cliente Mesa 7',
            'items'          => [
                [
                    'dish_id'  => $this->dishTacos->id,
                    'quantity' => 1,
                    'extras'   => [$this->extraChampagne->id], // Extra no autorizado
                ]
            ]
        ];

        $response = $this->postJson('/api/orders', $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['extras']);
    }

    public function test_unauthorized_role_cannot_add_dishes(): void
    {
        $payload = [
            'order_id' => $this->activeOrder->id,
            'dish_id'  => $this->dishTacos->id,
            'quantity' => 1,
        ];

        // 1. Sin autenticar -> 401
        $guestRes = $this->postJson("/api/orders/{$this->activeOrder->id}/items", $payload);
        $guestRes->assertStatus(401);

        // 2. Repartidor no autorizado -> 403
        $driverRes = $this->actingAs($this->repartidor)
            ->postJson("/api/orders/{$this->activeOrder->id}/items", $payload);
        $driverRes->assertStatus(403);
    }
}
