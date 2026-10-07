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

    public function test_forgot_password_generates_6_digit_otp_and_dispatches_n8n_webhook(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success'], 200),
        ]);

        $user = User::factory()->create([
            'name'  => 'Carlos Chef',
            'email' => 'carlos@aurum.com',
        ]);

        $response = $this->postJson('/api/password/forgot', [
            'email' => 'carlos@aurum.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('password_resets', [
            'email' => 'carlos@aurum.com',
        ]);

        $record = \Illuminate\Support\Facades\DB::table('password_resets')->where('email', 'carlos@aurum.com')->first();
        $this->assertNotNull($record);
        $this->assertEquals(6, strlen($record->code));
        $this->assertTrue(is_numeric($record->code));

        Http::assertSent(function ($request) use ($user, $record) {
            return $request['email'] === $user->email
                && $request['name'] === $user->name
                && $request['code'] === $record->code;
        });
    }

    public function test_forgot_password_returns_404_when_user_does_not_exist(): void
    {
        $response = $this->postJson('/api/password/forgot', [
            'email' => 'no_existe@aurum.com',
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Ese correo no está registrado a ningún usuario dentro del sistema',
        ]);
    }

    public function test_forgot_password_aborts_with_403_when_honeypot_is_filled(): void
    {
        $response = $this->postJson('/api/password/forgot', [
            'email'       => 'carlos@aurum.com',
            'website_url' => 'https://spam-bot.com',
        ]);

        $response->assertStatus(403);
    }

    public function test_forgot_password_returns_404_when_user_is_inactive(): void
    {
        $inactiveUser = User::factory()->create([
            'email'     => 'inactivo@aurum.com',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/password/forgot', [
            'email' => 'inactivo@aurum.com',
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Ese correo no está registrado a ningún usuario dentro del sistema',
        ]);
    }

    public function test_forgot_password_returns_429_during_otp_cooldown(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success'], 200),
        ]);

        $user = User::factory()->create([
            'email'     => 'cooldown@aurum.com',
            'is_active' => true,
        ]);

        // Primera petición: éxito 200
        $res1 = $this->postJson('/api/password/forgot', ['email' => 'cooldown@aurum.com']);
        $res1->assertStatus(200);

        // Segunda petición inmediata: rechazo 429 por cooldown de 2 minutos
        $res2 = $this->postJson('/api/password/forgot', ['email' => 'cooldown@aurum.com']);
        $res2->assertStatus(429);
        $res2->assertJson([
            'message' => 'Ya enviamos un código a este correo. Por favor, espera 2 minutos antes de solicitar otro.',
        ]);
    }

    public function test_forgot_password_handles_webhook_failure_gracefully(): void
    {
        Http::fake([
            '*' => function () {
                throw new \Exception('Conexión rehusada con el webhook de n8n');
            },
        ]);

        $user = User::factory()->create([
            'name'  => 'Fallo Webhook',
            'email' => 'fallo_webhook@aurum.com',
        ]);

        $response = $this->postJson('/api/password/forgot', [
            'email' => 'fallo_webhook@aurum.com',
        ]);

        // Retorna 200 OK informando que se ha enviado sin importar si el webhook falló
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
    }

    public function test_reset_password_with_valid_otp_updates_user_password_and_cleans_db(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success'], 200),
        ]);

        $user = User::factory()->create([
            'name'                 => 'Laura Mesera',
            'email'                => 'laura@aurum.com',
            'password'             => \Illuminate\Support\Facades\Hash::make('OldPassword123!'),
            'must_change_password' => true,
        ]);

        $this->postJson('/api/password/forgot', ['email' => 'laura@aurum.com']);

        $record = \Illuminate\Support\Facades\DB::table('password_resets')->where('email', 'laura@aurum.com')->first();
        $this->assertNotNull($record);

        $response = $this->postJson('/api/password/reset', [
            'email'                     => 'laura@aurum.com',
            'code'                      => $record->code,
            'new_password'              => 'NuevaPasswordSegura2026!',
            'new_password_confirmation' => 'NuevaPasswordSegura2026!',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);

        $user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NuevaPasswordSegura2026!', $user->password));
        $this->assertFalse($user->must_change_password);

        // Verifica que el código se haya eliminado de la base de datos
        $this->assertDatabaseMissing('password_resets', [
            'email' => 'laura@aurum.com',
        ]);
    }

    public function test_reset_password_fails_with_expired_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'expirado@aurum.com',
        ]);

        \Illuminate\Support\Facades\DB::table('password_resets')->insert([
            'email'      => 'expirado@aurum.com',
            'code'       => '123456',
            'token'      => '123456',
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);

        $response = $this->postJson('/api/password/reset', [
            'email'                     => 'expirado@aurum.com',
            'code'                      => '123456',
            'new_password'              => 'NuevaPasswordSegura2026!',
            'new_password_confirmation' => 'NuevaPasswordSegura2026!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'El código de verificación ha expirado (límite de 2 minutos). Por favor solicita uno nuevo.',
        ]);
    }

    public function test_reset_password_fails_with_invalid_code(): void
    {
        $user = User::factory()->create([
            'email' => 'invalido@aurum.com',
        ]);

        \Illuminate\Support\Facades\DB::table('password_resets')->insert([
            'email'      => 'invalido@aurum.com',
            'code'       => '654321',
            'token'      => '654321',
            'expires_at' => now()->addMinutes(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/password/reset', [
            'email'                     => 'invalido@aurum.com',
            'code'                      => '999999',
            'new_password'              => 'NuevaPasswordSegura2026!',
            'new_password_confirmation' => 'NuevaPasswordSegura2026!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'El código de verificación de 6 dígitos es incorrecto.',
        ]);
    }

    public function test_verify_code_succeeds_with_valid_otp(): void
    {
        \Illuminate\Support\Facades\DB::table('password_resets')->insert([
            'email'      => 'verify_valid@aurum.com',
            'code'       => '123456',
            'token'      => '123456',
            'expires_at' => now()->addMinutes(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/password/verify-code', [
            'email' => 'verify_valid@aurum.com',
            'code'  => '123456',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Código verificado correctamente',
        ]);
    }

    public function test_verify_code_fails_with_invalid_or_expired_code(): void
    {
        \Illuminate\Support\Facades\DB::table('password_resets')->insert([
            'email'      => 'verify_invalid@aurum.com',
            'code'       => '111222',
            'token'      => '111222',
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);

        // Código erróneo
        $res1 = $this->postJson('/api/password/verify-code', [
            'email' => 'verify_invalid@aurum.com',
            'code'  => '999999',
        ]);
        $res1->assertStatus(422);
        $res1->assertJson([
            'message' => 'Por favor verifique bien el código porque está mal escrito o ha expirado.',
        ]);

        // Código expirado
        $res2 = $this->postJson('/api/password/verify-code', [
            'email' => 'verify_invalid@aurum.com',
            'code'  => '111222',
        ]);
        $res2->assertStatus(422);
        $res2->assertJson([
            'message' => 'Por favor verifique bien el código porque está mal escrito o ha expirado.',
        ]);
    }

    public function test_reset_password_fails_if_password_does_not_meet_security_policy(): void
    {
        $user = User::factory()->create([
            'email' => 'seguridad@aurum.com',
        ]);

        \Illuminate\Support\Facades\DB::table('password_resets')->insert([
            'email'      => 'seguridad@aurum.com',
            'code'       => '123456',
            'token'      => '123456',
            'expires_at' => now()->addMinutes(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sin símbolos ni números
        $res1 = $this->postJson('/api/password/reset', [
            'email'                     => 'seguridad@aurum.com',
            'code'                      => '123456',
            'new_password'              => 'sololetrasgrandesychicas',
            'new_password_confirmation' => 'sololetrasgrandesychicas',
        ]);
        $res1->assertStatus(422);
        $res1->assertJsonValidationErrors(['new_password']);

        // Sin símbolos
        $res2 = $this->postJson('/api/password/reset', [
            'email'                     => 'seguridad@aurum.com',
            'code'                      => '123456',
            'new_password'              => 'Password1234',
            'new_password_confirmation' => 'Password1234',
        ]);
        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['new_password']);

        // Sin números
        $res3 = $this->postJson('/api/password/reset', [
            'email'                     => 'seguridad@aurum.com',
            'code'                      => '123456',
            'new_password'              => 'Password!@#$',
            'new_password_confirmation' => 'Password!@#$',
        ]);
        $res3->assertStatus(422);
        $res3->assertJsonValidationErrors(['new_password']);
    }
}

