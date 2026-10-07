<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewUserRegistrationWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user_without_password_and_dispatches_n8n_webhook(): void
    {
        putenv('N8N_NEW_USER_WEBHOOK_URL=http://localhost:5678/webhook/nuevo-usuario');

        Http::fake([
            'http://localhost:5678/webhook/nuevo-usuario' => Http::response(['status' => 'success'], 200),
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/admin/usuarios', [
            'name'  => 'Carlos Cajero',
            'phone' => '7441112233',
            'email' => 'carlos.cajero@aurum.com',
            'role'  => 'cajero',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email', 'phone', 'role'],
        ]);

        $this->assertDatabaseHas('users', [
            'email'                => 'carlos.cajero@aurum.com',
            'role'                 => 'cajero',
            'must_change_password' => true,
        ]);

        $createdUser = User::where('email', 'carlos.cajero@aurum.com')->first();
        $this->assertNotNull($createdUser->password);

        Http::assertSent(function ($request) use ($createdUser) {
            return $request->url() === 'http://localhost:5678/webhook/nuevo-usuario'
                && $request['email'] === 'carlos.cajero@aurum.com'
                && $request['phone'] === '7441112233'
                && $request['role'] === 'cajero'
                && !empty($request['password'])
                && Hash::check($request['password'], $createdUser->password);
        });
    }

    public function test_user_creation_succeeds_even_if_n8n_webhook_fails(): void
    {
        putenv('N8N_NEW_USER_WEBHOOK_URL=http://localhost:5678/webhook/nuevo-usuario');

        Http::fake([
            'http://localhost:5678/webhook/nuevo-usuario' => function () {
                throw new \Exception('Conexión rehusada con el webhook');
            },
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/admin/usuarios', [
            'name'  => 'Maria Mesera',
            'phone' => '7449998877',
            'email' => 'maria.mesera@aurum.com',
            'role'  => 'mesero',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'maria.mesera@aurum.com',
        ]);
    }
}
