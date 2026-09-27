<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KitchenOrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected User $kitchenUser;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kitchenUser = User::create([
            'name' => 'Cocinero Jefe',
            'email' => 'cocina@aurum.com',
            'password' => Hash::make('password'),
            'role' => 'cocina',
        ]);

        $category = Category::create([
            'name' => 'Principales',
            'slug' => 'principales',
            'active' => true,
        ]);

        $dish = Dish::create([
            'category_id' => $category->id,
            'name' => 'Ribeye Steak',
            'slug' => 'ribeye-steak',
            'price' => 350.00,
            'is_available' => true,
        ]);

        $this->order = Order::create([
            'folio' => 'PED202609200050',
            'customer_name' => 'Comensal Mesa 5',
            'customer_phone' => '5551234567',
            'customer_address' => 'Mesa 5',
            'modality' => 'local',
            'table_number' => '5',
            'status' => 'pending',
            'total_amount' => 350.00,
            'subtotal' => 350.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);
    }

    public function test_can_advance_order_from_pending_to_preparing(): void
    {
        $response = $this->actingAs($this->kitchenUser)
            ->patchJson("/api/kitchen/orders/{$this->order->id}/status", [
                'status' => 'en_preparacion',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'preparing');

        $this->assertEquals('preparing', $this->order->fresh()->status);
    }

    public function test_preparing_status_is_idempotent_when_already_preparing(): void
    {
        $this->order->update(['status' => 'preparing']);

        $response = $this->actingAs($this->kitchenUser)
            ->patchJson("/api/kitchen/orders/{$this->order->id}/status", [
                'status' => 'en_preparacion',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'preparing');
    }

    public function test_can_advance_order_to_ready_or_completed(): void
    {
        $this->order->update(['status' => 'preparing']);

        $response = $this->actingAs($this->kitchenUser)
            ->patchJson("/api/kitchen/orders/{$this->order->id}/status", [
                'status' => 'listo',
            ]);

        $response->assertStatus(200);
        // Para modalidad local se mapea a completed
        $this->assertEquals('completed', $this->order->fresh()->status);
    }

    public function test_terminating_already_finished_order_returns_200_ok_idempotently(): void
    {
        // Simulamos pedido 50 ya completado/terminado en PostgreSQL
        $this->order->update(['status' => 'completed']);

        $response = $this->actingAs($this->kitchenUser)
            ->patchJson("/api/kitchen/orders/{$this->order->id}/status", [
                'status' => 'listo',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'completed');
    }

    public function test_supports_spanish_alias_terminado(): void
    {
        $this->order->update(['status' => 'preparing']);

        $response = $this->actingAs($this->kitchenUser)
            ->patchJson("/api/kitchen/orders/{$this->order->id}/status", [
                'status' => 'terminado',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('completed', $this->order->fresh()->status);
    }
}
