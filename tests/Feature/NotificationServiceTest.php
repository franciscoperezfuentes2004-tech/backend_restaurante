<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\RestaurantSetting;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_order_notification_to_discord(): void
    {
        RestaurantSetting::updateOrCreate([], [
            'restaurant_name'              => 'Restaurante Gourmet',
            'active_notification_platform' => 'discord',
            'discord_settings'             => [
                'orders_webhook_url'       => 'https://discord.com/api/webhooks/kitchen-orders',
                'reservations_webhook_url' => 'https://discord.com/api/webhooks/reception',
            ],
        ]);

        Http::fake([
            'https://discord.com/api/webhooks/kitchen-orders' => Http::response(['id' => '123'], 200),
        ]);

        $category = Category::create(['name' => 'Comida', 'slug' => 'comida', 'is_active' => true]);
        $dish = Dish::create([
            'category_id' => $category->id,
            'name'        => 'Tacos al Pastor',
            'slug'        => 'tacos-al-pastor',
            'price'       => 50.00,
            'is_active'   => true,
        ]);

        $order = Order::create([
            'folio'            => 'ORD-001',
            'customer_name'    => 'Juan Perez',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Mesa 1',
            'table_number'     => '1',
            'modality'         => 'local',
            'total_amount'     => 100.00,
            'status'           => 'pending',
            'notes'            => 'Salsa aparte',
        ]);
        $order->items()->create([
            'dish_id'  => $dish->id,
            'quantity' => 2,
            'price'    => 50.00,
            'notes'    => 'Bien cocido',
        ]);

        $service = app(NotificationService::class);
        $result = $service->sendOrderNotification($order);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://discord.com/api/webhooks/kitchen-orders'
                && isset($request['embeds'][0]['title'])
                && str_contains($request['embeds'][0]['title'], 'ORD-001');
        });
    }

    public function test_send_order_notification_to_telegram(): void
    {
        RestaurantSetting::updateOrCreate([], [
            'restaurant_name'              => 'Restaurante Gourmet',
            'active_notification_platform' => 'telegram',
            'telegram_settings'            => [
                'bot_token'                => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
                'orders_chat_id'           => '-100123456789',
                'reservations_chat_id'     => '-100987654321',
            ],
        ]);

        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true], 200),
        ]);

        $order = Order::create([
            'folio'            => 'ORD-002',
            'customer_name'    => 'Maria Lopez',
            'customer_phone'   => '7449998877',
            'customer_address' => 'Av Costera 123',
            'modality'         => 'delivery',
            'total_amount'     => 150.00,
            'status'           => 'pending',
        ]);

        $service = app(NotificationService::class);
        $result = $service->sendOrderNotification($order);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11/sendMessage')
                && $request['chat_id'] === '-100123456789'
                && str_contains($request['text'], 'ORD-002');
        });
    }

    public function test_send_reservation_notification_to_discord(): void
    {
        RestaurantSetting::updateOrCreate([], [
            'restaurant_name'              => 'Restaurante Gourmet',
            'active_notification_platform' => 'discord',
            'discord_settings'             => [
                'orders_webhook_url'       => 'https://discord.com/api/webhooks/kitchen-orders',
                'reservations_webhook_url' => 'https://discord.com/api/webhooks/reception-res',
            ],
        ]);

        Http::fake([
            'https://discord.com/api/webhooks/reception-res' => Http::response(['id' => '456'], 200),
        ]);

        $reservation = Reservation::create([
            'nombre'         => 'Pedro Gomez',
            'telefono'       => '7445556677',
            'email'          => 'pedro@example.com',
            'fecha'          => '2026-10-25',
            'hora'           => '21:00',
            'personas'       => 4,
            'estado'         => 'confirmada',
            'table_number'   => 'Mesa 10',
            'folio'          => 'RES-9999',
        ]);

        $service = app(NotificationService::class);
        $result = $service->sendReservationNotification($reservation);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://discord.com/api/webhooks/reception-res'
                && str_contains($request['embeds'][0]['title'], 'RES-9999');
        });
    }

    public function test_notification_skipped_when_platform_is_none(): void
    {
        RestaurantSetting::updateOrCreate([], [
            'active_notification_platform' => 'none',
        ]);

        Http::fake();

        $order = Order::create([
            'folio'            => 'ORD-003',
            'customer_name'    => 'Ana Ruiz',
            'customer_phone'   => '7441113355',
            'customer_address' => 'Mesa 2',
            'total_amount'     => 80.00,
            'status'           => 'pending',
        ]);

        $service = app(NotificationService::class);
        $result = $service->sendOrderNotification($order);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }
}
