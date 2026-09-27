<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\DeliveryDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UpdateRepartidorTest extends TestCase
{
    use RefreshDatabase;

    public function test_repartidor_can_update_profile_and_keep_own_email(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'name'      => 'Carlos Méndez',
            'email'     => 'carlos@gmail.com',
            'phone'     => '7441112233',
            'role'      => 'repartidor',
            'is_active' => true,
        ]);

        $driver = DeliveryDriver::create([
            'name'    => 'Carlos Méndez',
            'phone'   => '7441112233',
            'email'   => 'carlos@gmail.com',
            'user_id' => $user->id,
            'active'  => true,
            'status'  => 'active',
        ]);

        $this->actingAs($admin, 'sanctum');

        $payload = [
            'nombre'   => 'Carlos Méndez Actualizado',
            'telefono' => '7449998877',
            'estado'   => true,
            'email'    => 'carlos@gmail.com', // Mismo correo -> NO debe fallar por unique
        ];

        $response = $this->putJson('/api/admin/repartidores/' . $user->id, $payload);

        $response->assertStatus(200)
                 ->assertJson([
                     'mensaje' => 'Repartidor actualizado correctamente',
                 ]);

        $this->assertDatabaseHas('users', [
            'id'        => $user->id,
            'name'      => 'Carlos Méndez Actualizado',
            'phone'     => '7449998877',
            'email'     => 'carlos@gmail.com',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('delivery_drivers', [
            'id'     => $driver->id,
            'name'   => 'Carlos Méndez Actualizado',
            'phone'  => '7449998877',
            'email'  => 'carlos@gmail.com',
            'active' => true,
        ]);
    }

    public function test_updating_repartidor_purifies_html_tags_anti_xss(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'name'      => 'Laura Gómez',
            'email'     => 'laura@gmail.com',
            'phone'     => '7442223344',
            'role'      => 'repartidor',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum');

        $payload = [
            'nombre'   => '<b>Laura <script>alert("xss")</script>Gómez</b>',
            'telefono' => '7442223344',
            'estado'   => false,
            'email'    => 'laura@gmail.com',
        ];

        $response = $this->putJson('/api/admin/repartidores/' . $user->id, $payload);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals('Laura Gómez', $user->name);
        $this->assertFalse($user->is_active);
    }

    public function test_updating_repartidor_fails_if_email_is_taken_by_another_user(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $otherUser = User::factory()->create([
            'name'  => 'Otro Usuario',
            'email' => 'otro@gmail.com',
            'phone' => '7443334455',
            'role'  => 'repartidor',
        ]);

        $user = User::factory()->create([
            'name'  => 'Pedro Torres',
            'email' => 'pedro@gmail.com',
            'phone' => '7445556677',
            'role'  => 'repartidor',
        ]);

        $this->actingAs($admin, 'sanctum');

        $payload = [
            'nombre'   => 'Pedro Torres',
            'telefono' => '7445556677',
            'estado'   => true,
            'email'    => 'otro@gmail.com', // Correo ya registrado por otro usuario
        ];

        $response = $this->putJson('/api/admin/repartidores/' . $user->id, $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    public function test_updating_repartidor_validates_required_and_formatted_fields(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'name'  => 'Mario Bros',
            'email' => 'mario@gmail.com',
            'phone' => '7441112233',
            'role'  => 'repartidor',
        ]);

        $this->actingAs($admin, 'sanctum');

        // Teléfono inválido (menos de 10 dígitos), nombre menor a 3 caracteres
        $response = $this->putJson('/api/admin/repartidores/' . $user->id, [
            'nombre'   => 'Al',
            'telefono' => '123',
            'estado'   => 'no-boolean',
            'email'    => 'correo-invalido',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['nombre', 'telefono', 'estado', 'email']);
    }

    public function test_driver_controller_update_endpoint_also_supports_updating_by_driver_id(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'name'      => 'Driver Name',
            'email'     => 'driver@gmail.com',
            'phone'     => '7448889900',
            'role'      => 'repartidor',
            'is_active' => true,
        ]);

        $driver = DeliveryDriver::create([
            'name'    => 'Driver Name',
            'phone'   => '7448889900',
            'email'   => 'driver@gmail.com',
            'user_id' => $user->id,
            'active'  => true,
            'status'  => 'active',
        ]);

        $this->actingAs($admin, 'sanctum');

        // Actualización a través de /api/admin/drivers/{driver_id} usando campos en español
        $response = $this->putJson('/api/admin/drivers/' . $driver->id, [
            'nombre'   => 'Driver Actualizado',
            'telefono' => '7449991122',
            'estado'   => false,
            'email'    => 'driver@gmail.com', // Mismo correo
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'mensaje' => 'Repartidor actualizado correctamente',
                 ]);

        $driver->refresh();
        $user->refresh();

        $this->assertEquals('Driver Actualizado', $driver->name);
        $this->assertEquals('7449991122', $driver->phone);
        $this->assertFalse($driver->active);
        $this->assertEquals('inactive', $driver->status);

        $this->assertEquals('Driver Actualizado', $user->name);
        $this->assertFalse($user->is_active);
    }
}