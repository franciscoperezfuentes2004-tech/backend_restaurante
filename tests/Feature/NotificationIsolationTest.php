<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-17 17:00:00'));
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test 1: Aislamiento de usuario - El usuario solo recibe sus propias notificaciones.
     */
    public function test_usuario_solo_ve_sus_propias_notificaciones_y_no_las_de_otros(): void
    {
        $userA = User::factory()->create(['role' => 'mesero']);
        $userB = User::factory()->create(['role' => 'mesero']);

        // Notificación para User A
        Notification::create([
            'user_id' => $userA->id,
            'type'    => 'order_ready',
            'title'   => 'Pedido Listo',
            'message' => 'Pedido #1 listo para entrega',
        ]);

        // Notificación para User B
        Notification::create([
            'user_id' => $userB->id,
            'type'    => 'order_ready',
            'title'   => 'Pedido Listo',
            'message' => 'Pedido #2 listo para entrega de User B',
        ]);

        $response = $this->actingAs($userA)->getJson('/api/admin/notifications');

        $response->assertStatus(200);
        $notificaciones = $response->json('notificaciones');
        $this->assertCount(1, $notificaciones);
        $this->assertEquals('Pedido #1 listo para entrega', $notificaciones[0]['mensaje']);
    }

    /**
     * Test 2: Soft delete al limpiar todo - Preserva el historial en PostgreSQL.
     */
    public function test_limpiar_todo_aplica_soft_delete_sin_borrado_fisico(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $notif = Notification::create([
            'user_id' => $user->id,
            'type'    => 'test_alert',
            'title'   => 'Alerta de Seguridad',
            'message' => 'Notificación de prueba',
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/admin/notifications');

        $response->assertStatus(200);

        // La notificación ya no es visible para el usuario
        $listResponse = $this->actingAs($user)->getJson('/api/admin/notifications');
        $this->assertCount(0, $listResponse->json('notificaciones'));

        // Pero la fila física SIGUE existiendo en PostgreSQL con deleted_at (Auditoría preservada)
        $this->assertDatabaseHas('notifications', [
            'id' => $notif->id,
        ]);
        $this->assertNotNull(Notification::withTrashed()->find($notif->id)->deleted_at);
    }

    /**
     * Test 3: Marcar todas como leídas.
     */
    public function test_marcar_todas_como_leidas(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $notif = Notification::create([
            'user_id' => $user->id,
            'type'    => 'test_unread',
            'title'   => 'Nueva Orden',
            'message' => 'Orden recibida',
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)->postJson('/api/admin/notifications/read-all');

        $response->assertStatus(200);
        $notif->refresh();
        $this->assertNotNull($notif->read_at);
    }

    /**
     * Test 4: Enmascaramiento de email en notificaciones de login.
     */
    public function test_enmascaramiento_de_email_en_notificaciones(): void
    {
        $masked = NotificationService::maskEmail('francisco.perez@gmail.com');
        $this->assertEquals('fra***@gmail.com', $masked);
        $this->assertStringNotContainsString('francisco.perez@', $masked);

        $user = User::factory()->create([
            'email'    => 'francisco@aurum.mx',
            'password' => bcrypt('password123'),
            'role'     => 'admin',
        ]);

        $response = $this->postJson('/api/login', [
            'identifier' => 'francisco@aurum.mx',
            'password'   => 'password123',
        ]);

        $response->assertStatus(200);

        $lastNotification = Notification::where('type', 'login_success')->latest('id')->first();
        $this->assertNotNull($lastNotification);
        $this->assertStringContainsString('fra***@aurum.mx', $lastNotification->message);
        $this->assertStringNotContainsString('francisco@aurum.mx', $lastNotification->message);
    }
}