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
            return $request->url() === env('N8N_WEBHOOK_PASSWORD', 'http://localhost/webhook/password')
                && $request['telefono'] === $user->phone
                && $request['password_temporal'] === $tempPassword;
        });
    }

    public function test_generate_temp_password_requires_existing_email(): void
    {
        $response = $this->postJson('/api/password/reset-temp', [
            'email' => 'inexistente@restaurante.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }
}
