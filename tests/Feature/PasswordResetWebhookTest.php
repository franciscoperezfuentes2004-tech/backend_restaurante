<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PasswordResetWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_temp_password_dispatches_n8n_webhook(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success'], 200),
        ]);

        $user = User::factory()->create([
            'email' => 'empleado@restaurante.com',
            'phone' => '5512345678',
            'must_change_password' => false,
        ]);

        $response = $this->postJson('/api/password/reset-temp', [
            'email' => 'empleado@restaurante.com',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'temp_password_debug',
        ]);

        $user->refresh();
        $this->assertTrue($user->must_change_password);
        $tempPassword = $response->json('temp_password_debug');
        $this->assertTrue(Hash::check($tempPassword, $user->password));

        Http::assertSent(function ($request) use ($user, $tempPassword) {
            return $request['email'] === $user->email
                && $request['telefono'] === $user->phone
                && $request['codigo'] === $tempPassword
                && $request['password_temporal'] === $tempPassword;
        });
    }

    public function test_enviar_recuperacion_dispatches_n8n_webhook(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success'], 200),
        ]);

        $user = User::factory()->create([
            'email' => 'cliente@correo.com',
            'phone' => '7441234567',
            'must_change_password' => false,
        ]);

        $response = $this->postJson('/api/password/recuperar', [
            'email' => 'cliente@correo.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Se ha enviado el código de recuperación a tu correo y al sistema.',
        ]);

        $user->refresh();
        $this->assertTrue($user->must_change_password);
        $tempPassword = $response->json('temp_password_debug');
        $this->assertStringStartsWith('TEMP-', $tempPassword);
        $this->assertTrue(Hash::check($tempPassword, $user->password));

        Http::assertSent(function ($request) use ($user, $tempPassword) {
            return $request['email'] === $user->email
                && $request['telefono'] === $user->phone
                && $request['codigo'] === $tempPassword;
        });
    }

    public function test_enviar_recuperacion_handles_failure_gracefully(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'n8n down'], 500),
        ]);

        $user = User::factory()->create([
            'email' => 'fallo@correo.com',
        ]);

        $response = $this->postJson('/api/password/recuperar', [
            'email' => 'fallo@correo.com',
        ]);

        $response->assertStatus(500);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Ocurrió un error al procesar tu solicitud.',
        ]);
    }

    public function test_enviar_recuperacion_requires_existing_email(): void
    {
        $response = $this->postJson('/api/password/recuperar', [
            'email' => 'inexistente@restaurante.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }
}

