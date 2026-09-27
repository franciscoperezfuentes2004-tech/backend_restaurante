<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BroadcastingAuthChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'fake-key',
                'secret' => 'fake-secret',
                'app_id' => 'fake-app-id',
                'options' => [
                    'cluster' => 'mt1',
                ],
            ],
        ]);

        $this->app->forgetInstance('Illuminate\Broadcasting\BroadcastManager');
        $this->app->forgetInstance('Illuminate\Contracts\Broadcasting\Factory');
        $this->app->forgetInstance('Illuminate\Contracts\Broadcasting\Broadcaster');

        require base_path('routes/channels.php');
    }

    public function test_authenticated_mesero_can_authenticate_broadcasting_channel_via_api_prefix(): void
    {
        $mesero = User::create([
            'name' => 'Mesero Juan',
            'email' => 'juan.mesero@aurum.com',
            'password' => Hash::make('password'),
            'role' => 'mesero',
        ]);

        Sanctum::actingAs($mesero, ['*']);

        $response = $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-orders',
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['auth']);
    }

    public function test_authenticated_mesero_can_authenticate_broadcasting_channel(): void
    {
        $mesero = User::create([
            'name' => 'Mesero Juan',
            'email' => 'juan.mesero@aurum.com',
            'password' => Hash::make('password'),
            'role' => 'mesero',
        ]);

        Sanctum::actingAs($mesero, ['*']);

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-orders',
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['auth']);
    }

    public function test_authenticated_kitchen_user_can_authenticate_kitchen_orders_channel(): void
    {
        $cocinero = User::create([
            'name' => 'Chef Mario',
            'email' => 'mario.cocina@aurum.com',
            'password' => Hash::make('password'),
            'role' => 'cocina',
        ]);

        Sanctum::actingAs($cocinero, ['*']);

        $response = $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-kitchen.orders',
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['auth']);
    }

    public function test_unauthenticated_request_is_rejected_on_broadcasting_auth(): void
    {
        $response = $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-orders',
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(401);
    }
}
