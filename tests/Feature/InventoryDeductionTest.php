<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Dish;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Delivery;
use App\Models\Stock;
use App\Models\Category;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InventoryDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected Dish $dish;
    protected Ingredient $pan;
    protected Ingredient $carne;
    protected Ingredient $queso;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Hamburguesas Gourmet',
            'slug' => 'hamburguesas-gourmet',
            'active' => true,
        ]);

        $this->dish = Dish::create([
            'category_id'  => $this->category->id,
            'name'         => 'Hamburguesa Clasica',
            'slug'         => 'hamburguesa-clasica',
            'price'        => 150.00,
            'is_available' => true,
        ]);

        $this->pan = Ingredient::create([
            'name'         => 'Pan Brioche',
            'category'     => 'Panaderia',
            'unit'         => 'pieza',
            'base_cost'    => 10.00,
            'stock_actual' => 50.00,
            'stock_minimo' => 10.00,
        ]);

        $this->carne = Ingredient::create([
            'name'         => 'Carne de Res 150g',
            'category'     => 'Carnes',
            'unit'         => 'gramos',
            'base_cost'    => 40.00,
            'stock_actual' => 1000.00,
            'stock_minimo' => 200.00,
        ]);

        $this->queso = Ingredient::create([
            'name'         => 'Queso Cheddar',
            'category'     => 'Lacteos',
            'unit'         => 'rebanada',
            'base_cost'    => 5.00,
            'stock_actual' => 5.00,
            'stock_minimo' => 10.00,
        ]);

        // Receta: 1 Pan, 150g Carne, 1 Queso por hamburguesa
        $this->dish->ingredientes()->attach([
            $this->pan->id   => ['cantidad_requerida' => 1.0],
            $this->carne->id => ['cantidad_requerida' => 150.0],
            $this->queso->id => ['cantidad_requerida' => 1.0],
        ]);

        // Vincular tabla stock
        Stock::create(['ingredient_id' => $this->pan->id, 'quantity' => 50, 'min_quantity' => 10]);
        Stock::create(['ingredient_id' => $this->carne->id, 'quantity' => 1000, 'min_quantity' => 200]);
        Stock::create(['ingredient_id' => $this->queso->id, 'quantity' => 5, 'min_quantity' => 10]);
    }

    public function test_inventory_service_deducts_ingredients_proportionally_to_order_quantity(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Stock bajo para: Queso Cheddar');
            });

        // Crear pedido con 2 hamburguesas
        $order = Order::create([
            'folio'            => 'DEL202609160001',
            'modality'         => 'delivery',
            'status'           => 'pending',
            'total_amount'     => 300.00,
            'customer_name'    => 'Cliente Hambriento',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Av. Costera 123',
        ]);

        $order->items()->create([
            'dish_id'  => $this->dish->id,
            'quantity' => 2,
            'price'    => 150.00,
        ]);

        // Ejecutar descuento de inventario
        $success = InventoryService::descontarInventario($order->id);
        $this->assertTrue($success);

        // Verificaciones de stock_actual (50 - 2 = 48 panes)
        $this->pan->refresh();
        $this->assertEquals(48.00, $this->pan->stock_actual);

        // Verificación de carne (1000 - (150 * 2) = 700 gramos)
        $this->carne->refresh();
        $this->assertEquals(700.00, $this->carne->stock_actual);

        // Verificación de queso (5 - 2 = 3 rebanadas)
        $this->queso->refresh();
        $this->assertEquals(3.00, $this->queso->stock_actual);
    }

    public function test_delivery_model_descontar_inventario_works_with_delivery_instance(): void
    {
        $order = Order::create([
            'folio'            => 'DEL202609160002',
            'modality'         => 'delivery',
            'status'           => 'pending',
            'total_amount'     => 450.00,
            'customer_name'    => 'Cliente Delivery',
            'customer_phone'   => '7442223344',
            'customer_address' => 'Calle 5 #50',
        ]);

        $order->items()->create([
            'dish_id'  => $this->dish->id,
            'quantity' => 3,
            'price'    => 150.00,
        ]);

        $delivery = $order->delivery;
        $this->assertNotNull($delivery);

        // Descontar usando Delivery::descontarInventario
        $success = Delivery::descontarInventario($delivery->id);
        $this->assertTrue($success);

        $this->pan->refresh();
        $this->assertEquals(47.00, $this->pan->stock_actual);

        $this->carne->refresh();
        $this->assertEquals(550.00, $this->carne->stock_actual);
    }

    public function test_updating_order_status_to_preparing_triggers_automatic_inventory_deduction(): void
    {
        $order = Order::create([
            'folio'            => 'DEL202609160003',
            'modality'         => 'delivery',
            'status'           => 'pending',
            'total_amount'     => 150.00,
            'customer_name'    => 'Cliente Cocina',
            'customer_phone'   => '7443334455',
            'customer_address' => 'Calle Cocina #1',
        ]);

        $order->items()->create([
            'dish_id'  => $this->dish->id,
            'quantity' => 1,
            'price'    => 150.00,
        ]);

        $user = User::factory()->create(['role' => 'kitchen']);

        $response = $this->actingAs($user)->putJson("/api/orders/{$order->id}/status", [
            'order_id' => $order->id,
            'status'   => 'en_preparacion',
        ]);

        $response->assertStatus(200);

        $this->pan->refresh();
        $this->assertEquals(49.00, $this->pan->stock_actual);
    }
}