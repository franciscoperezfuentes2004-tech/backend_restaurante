<?php

namespace Tests\Feature;

use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Jobs\SendKitchenNotification;
use App\Jobs\SendOrderToN8n;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueJobsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Dish $dish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'mesero']);

        $category = Category::create([
            'name'      => 'Bebidas',
            'slug'      => 'bebidas',
            'is_active' => true,
        ]);

        $this->dish = Dish::create([
            'category_id' => $category->id,
            'name'        => 'Limonada Mineral',
            'slug'        => 'limonada-mineral',
            'price'       => 45.00,
            'is_active'   => true,
        ]);
    }

    /**
     * Test: SendOrderToN8n ejecuta la notificación multi-canal directamente a Discord/Telegram.
     */
    public function test_send_order_to_n8n_executes_http_post_with_order_payload(): void
    {
        \App\Models\RestaurantSetting::updateOrCreate([], [
            'restaurant_name'              => 'Aurum Test',
            'active_notification_platform' => 'discord',
            'discord_settings'             => [
                'orders_webhook_url'       => 'https://discord.com/api/webhooks/test-orders',
                'reservations_webhook_url' => 'https://discord.com/api/webhooks/test-reservations',
            ],
        ]);

        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response(['success' => true], 200),
        ]);

        $order = Order::create([
            'folio'            => 'PED202609260001',
            'customer_name'    => 'Carlos Santana',
            'customer_phone'   => '7441234567',
            'customer_address' => 'Mesa 4',
            'table_number'     => '4',
            'modality'         => 'local',
            'total_amount'     => 90.00,
            'status'           => 'pending',
            'notes'            => 'Sin hielo',
        ]);

        $order->items()->create([
            'dish_id'  => $this->dish->id,
            'quantity' => 2,
            'price'    => 45.00,
            'notes'    => 'Sin hielo',
        ]);

        $job = new SendOrderToN8n($order);
        $job->handle();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'discord.com/api/webhooks/test-orders')
                && isset($request['embeds'])
                && count($request['embeds']) > 0;
        });
    }

    /**
     * Test: SendKitchenNotification genera registro en BD y emite WebSockets.
     */
    public function test_send_kitchen_notification_creates_db_notification_and_broadcasts(): void
    {
        Event::fake([OrderCreated::class, OrderStatusUpdated::class]);

        $order = Order::create([
            'folio'            => 'PED202609260002',
            'customer_name'    => 'Mariana Garza',
            'customer_phone'   => '7449876543',
            'customer_address' => 'Av. Costera 100',
            'modality'         => 'delivery',
            'total_amount'     => 135.00,
            'status'           => 'pending',
        ]);

        $order->items()->create([
            'dish_id'  => $this->dish->id,
            'quantity' => 3,
            'price'    => 45.00,
        ]);

        $job = new SendKitchenNotification($order, true);
        $job->handle();

        $this->assertDatabaseHas('notifications', [
            'type'  => 'pedido_nuevo',
            'title' => 'Nuevo Pedido en Línea',
        ]);

        Event::assertDispatched(OrderCreated::class);
        Event::assertDispatched(OrderStatusUpdated::class);
    }

    /**
     * Test: Al crear una orden vía API, se encolan los Jobs en segundo plano.
     */
    public function test_creating_order_via_api_pushes_jobs_to_queue(): void
    {
        Queue::fake();

        $payload = [
            'nombre_completo' => 'Juan Mecánico',
            'telefono'        => '7445558899',
            'order_type'      => 'pickup',
            'payment_method'  => 'cash',
            'items'           => [
                [
                    'dish_id'  => $this->dish->id,
                    'quantity' => 1,
                ]
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', $payload);

        $response->assertStatus(201);

        Queue::assertPushed(SendOrderToN8n::class);
        Queue::assertPushed(SendKitchenNotification::class);
    }
}
