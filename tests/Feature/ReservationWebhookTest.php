<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReservationWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_asignar_mesa_updates_reservation_and_dispatches_n8n_webhook(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'Workflow was started'], 200),
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $reservation = Reservation::create([
            'nombre'           => 'Carlos Sánchez',
            'telefono'         => '7441234567',
            'email'            => 'correo_del_cliente@gmail.com',
            'fecha'            => '2026-10-15',
            'hora'             => '20:30',
            'personas'         => 4,
            'estado'           => 'pendiente',
            'zona_preferida'   => 'Terraza',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/reservations/{$reservation->id}/asignar-mesa", [
                'numero_mesa' => '5',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status'  => 'success',
            'message' => 'Mesa asignada y correo de confirmación enviado al cliente.',
        ]);

        $reservation->refresh();
        $this->assertEquals('confirmed', $reservation->status);
        $this->assertEquals('confirmada', $reservation->estado);
        $this->assertEquals('Mesa 5', $reservation->table_number);

        Http::assertSent(function ($request) use ($reservation) {
            return $request['cliente_email'] === 'correo_del_cliente@gmail.com'
                && $request['cliente_nombre'] === 'Carlos Sánchez'
                && $request['folio'] === $reservation->folio
                && $request['personas'] === 4
                && $request['mesa'] === 'Mesa 5';
        });
    }

    public function test_update_status_to_confirmed_dispatches_n8n_webhook(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'Workflow was started'], 200),
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $reservation = Reservation::create([
            'nombre'           => 'Ana López',
            'telefono'         => '7449876543',
            'email'            => 'ana@gmail.com',
            'fecha'            => '2026-10-20',
            'hora'             => '19:00',
            'personas'         => 2,
            'estado'           => 'pendiente',
            'zona_preferida'   => 'Terraza',
        ]);

        $response = $this->actingAs($admin)
            ->patchJson("/api/admin/reservations/{$reservation->id}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'webhook');
        });
    }
}
