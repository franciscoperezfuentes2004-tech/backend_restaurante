<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PasswordResetController extends Controller
{
    /**
     * POST /api/password/forgot
     * Genera código OTP de 6 dígitos con caducidad de 2 minutos y dispara webhook n8n.
     */
    public function forgotPassword(Request $request)
    {
        // 1. Validación de Honeypot (Trampa para bots)
        if ($request->filled('website_url') || $request->filled('phone_ext') || !empty($request->input('website_url')) || !empty($request->input('phone_ext'))) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email'    => 'Ingrese un correo electrónico válido.',
        ]);

        $email = strtolower(trim($request->email));
        $user = User::where('email', $email)->first();

        // 2. Verificación de Estado (Active Check) y Registro de Auditoría (Logging)
        $isInactive = false;
        if ($user) {
            if (isset($user->is_active) && !$user->is_active) {
                $isInactive = true;
            }
            if (isset($user->status) && in_array(strtolower($user->status), ['inactivo', 'suspendido', 'disabled'])) {
                $isInactive = true;
            }
        }

        if (!$user || $isInactive) {
            \Log::warning('Intento de recuperación fallido. Correo no encontrado o inactivo.', [
                'email' => $request->email,
                'ip'    => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Ese correo no está registrado a ningún usuario dentro del sistema',
            ], 404);
        }

        // Asegurar que la tabla password_resets exista para evitar fallos de migración
        if (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function ($table) {
                $table->id();
                $table->string('email')->index();
                $table->string('code', 10);
                $table->string('token')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        // 3. Cooldown de OTP (2 minutos)
        $existingReset = DB::table('password_resets')
            ->where('email', $email)
            ->where('expires_at', '>', now())
            ->first();

        if ($existingReset) {
            return response()->json([
                'message' => 'Ya enviamos un código a este correo. Por favor, espera 2 minutos antes de solicitar otro.',
            ], 429);
        }

        // Generar código numérico aleatorio de 6 dígitos
        $otpCode = sprintf('%06d', random_int(0, 999999));
        $expiresAt = now()->addMinutes(2);

        // Guardar en tabla password_resets con caducidad estricta de 2 minutos
        DB::table('password_resets')->updateOrInsert(
            ['email' => $email],
            [
                'code'       => $otpCode,
                'token'      => $otpCode,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Sincronizar en password_reset_tokens si existe
        if (Schema::hasTable('password_reset_tokens')) {
            $dataToInsert = [
                'token'      => $otpCode,
                'created_at' => now(),
            ];
            if (Schema::hasColumn('password_reset_tokens', 'code')) {
                $dataToInsert['code'] = $otpCode;
            }
            if (Schema::hasColumn('password_reset_tokens', 'expires_at')) {
                $dataToInsert['expires_at'] = $expiresAt;
            }
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                $dataToInsert
            );
        }

        // Despacho del webhook de n8n encapsulado en try-catch robusto
        try {
            $webhookUrl = env('N8N_PASSWORD_WEBHOOK_URL') ?: env('N8N_WEBHOOK_URL') ?: env('N8N_WEBHOOK_PASSWORD');
            if (!empty($webhookUrl)) {
                Http::timeout(5)->post($webhookUrl, [
                    'email' => $user->email,
                    'name'  => $user->name,
                    'code'  => $otpCode,
                ]);
            } else {
                \Log::warning('N8N_PASSWORD_WEBHOOK_URL no configurado para envío de OTP.');
            }
        } catch (\Exception $e) {
            \Log::error('Fallo al enviar: ' . $e->getMessage());
            // Continuar la ejecución normal para no romper la respuesta al frontend
        } catch (\Throwable $e) {
            \Log::error('Fallo al enviar webhook n8n: ' . $e->getMessage());
            // Continuar la ejecución normal para no romper la respuesta al frontend
        }

        return response()->json([
            'status'     => 'success',
            'message'    => 'Código de verificación enviado exitosamente.',
            'code_debug' => app()->environment('local', 'testing') ? $otpCode : null,
        ], 200);
    }

    /**
     * POST /api/password/verify-code
     * Valida el código OTP de 6 dígitos antes de permitir el cambio de contraseña.
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string',
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email'    => 'Ingrese un correo electrónico válido.',
            'code.required'  => 'El código de 6 dígitos es obligatorio.',
        ]);

        $email     = strtolower(trim($request->email));
        $inputCode = trim((string) $request->code);

        $record = null;
        if (Schema::hasTable('password_resets')) {
            $record = DB::table('password_resets')->where('email', $email)->first();
        }
        if (!$record && Schema::hasTable('password_reset_tokens')) {
            $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        }

        if (!$record) {
            return response()->json([
                'message' => 'Por favor verifique bien el código porque está mal escrito o ha expirado.',
            ], 422);
        }

        $savedCode = (string) ($record->code ?? $record->token);
        if ($savedCode !== $inputCode) {
            return response()->json([
                'message' => 'Por favor verifique bien el código porque está mal escrito o ha expirado.',
            ], 422);
        }

        // Validar caducidad de 2 minutos
        $isExpired = false;
        if (!empty($record->expires_at)) {
            $isExpired = Carbon::parse($record->expires_at)->isPast();
        } elseif (!empty($record->created_at)) {
            $isExpired = Carbon::parse($record->created_at)->addMinutes(2)->isPast();
        }

        if ($isExpired) {
            if (Schema::hasTable('password_resets')) {
                DB::table('password_resets')->where('email', $email)->delete();
            }
            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
            }
            return response()->json([
                'message' => 'Por favor verifique bien el código porque está mal escrito o ha expirado.',
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Código verificado correctamente',
        ], 200);
    }

    /**
     * POST /api/password/reset
     * Verifica el código de 6 dígitos y caducidad, encripta y actualiza contraseña.
     */
    public function resetPassword(Request $request)
    {
        $hasNewPassword = $request->has('new_password');

        $rules = [
            'email' => 'required|email',
            'code'  => 'required|string',
        ];

        if ($hasNewPassword) {
            $rules['new_password']              = 'required|string|min:8|confirmed';
            $rules['new_password_confirmation'] = 'required|string';
            $passwordToSet                      = $request->new_password;
        } else {
            $rules['password']              = 'required|string|min:8|confirmed';
            $rules['password_confirmation'] = 'required|string';
            $passwordToSet                  = $request->password;
        }

        $request->validate($rules, [
            'email.required'          => 'El correo electrónico es obligatorio.',
            'email.email'             => 'Ingrese un correo electrónico válido.',
            'code.required'           => 'El código de 6 dígitos es obligatorio.',
            'new_password.required'   => 'La nueva contraseña es obligatoria.',
            'new_password.min'        => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'new_password.confirmed'  => 'La confirmación de la contraseña no coincide.',
            'password.required'       => 'La contraseña es obligatoria.',
            'password.min'            => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed'      => 'La confirmación de la contraseña no coincide.',
        ]);

        $email     = strtolower(trim($request->email));
        $inputCode = trim((string) $request->code);

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'message' => 'No se encontró una cuenta asociada a este correo electrónico.'
            ], 404);
        }

        $record = null;
        if (Schema::hasTable('password_resets')) {
            $record = DB::table('password_resets')->where('email', $email)->first();
        }
        if (!$record && Schema::hasTable('password_reset_tokens')) {
            $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        }

        if (!$record) {
            return response()->json([
                'message' => 'No hay ninguna solicitud de recuperación pendiente para este correo.'
            ], 422);
        }

        $savedCode = (string) ($record->code ?? $record->token);
        if ($savedCode !== $inputCode) {
            return response()->json([
                'message' => 'El código de verificación de 6 dígitos es incorrecto.'
            ], 422);
        }

        // Validar caducidad de 2 minutos
        $isExpired = false;
        if (!empty($record->expires_at)) {
            $isExpired = Carbon::parse($record->expires_at)->isPast();
        } elseif (!empty($record->created_at)) {
            $isExpired = Carbon::parse($record->created_at)->addMinutes(2)->isPast();
        }

        if ($isExpired) {
            if (Schema::hasTable('password_resets')) {
                DB::table('password_resets')->where('email', $email)->delete();
            }
            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
            }
            return response()->json([
                'message' => 'El código de verificación ha expirado (límite de 2 minutos). Por favor solicita uno nuevo.'
            ], 422);
        }

        // Actualizar contraseña del usuario
        $user->update([
            'password'             => Hash::make($passwordToSet),
            'must_change_password' => false,
        ]);

        // Eliminar código usado
        if (Schema::hasTable('password_resets')) {
            DB::table('password_resets')->where('email', $email)->delete();
        }
        if (Schema::hasTable('password_reset_tokens')) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Contraseña restablecida exitosamente. Ya puedes iniciar sesión con tu nueva contraseña.'
        ], 200);
    }
    public function enviarRecuperacion(Request $request)
    {
        // 1. Validas que el correo exista en tu base de datos de PostgreSQL
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        // 2. Generas tu código temporal (o token de recuperación)
        $codigoTemporal = 'TEMP-' . strtoupper(Str::random(4));

        // Opcional: Puedes guardarlo temporalmente en tu base de datos asociado al usuario
        // User::where('email', $request->email)->update(['recovery_code' => $codigoTemporal]);
        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->update([
                'password' => Hash::make($codigoTemporal),
                'must_change_password' => true,
            ]);
        }

        try {
            // Leemos la URL base de n8n desde el archivo .env
            // Si no existe, usamos localhost por defecto
            $n8nBaseUrl = rtrim(env('N8N_URL', 'http://localhost:5678'), '/');

            // Concatenamos la URL base con el path del webhook
            $response = Http::post($n8nBaseUrl . '/webhook/recuperar-password', [
                'email'             => $request->email,
                'telefono'          => $user?->phone ?? $user?->telefono ?? $request->telefono ?? 'N/A', // O el campo real que tengas en tu BD
                'codigo'            => $codigoTemporal,
                'password_temporal' => $codigoTemporal,
            ]);

            if ($response->successful()) {
                return response()->json([
                    'status'              => 'success',
                    'message'             => 'Se ha enviado el código de recuperación a tu correo y al sistema.',
                    'temp_password_debug' => $codigoTemporal,
                ], 200);
            }

        } catch (\Exception $e) {
            // Si n8n llegara a estar apagado, registras el error pero puedes manejarlo para no romper la app
            Log::error('Error al disparar webhook de n8n: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'error',
            'message' => 'Ocurrió un error al procesar tu solicitud.'
        ], 500);
    }

    public function generateTempPassword(Request $request)
    {
        return $this->enviarRecuperacion($request);
    }

    public function forceChange(Request $request)
    {
        // 1. Validar que envíe la nueva contraseña y que sea segura
        $request->validate([
            'new_password' => 'required|string|min:8|confirmed', 
            // "confirmed" exige que desde React envíen también "new_password_confirmation"
        ]);

        // 2. Guardar la nueva contraseña y apagar la bandera
        $request->user()->update([
            'password' => Hash::make($request->new_password),
            'must_change_password' => false
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada con éxito. Ya puedes usar el sistema.'
        ]);
    }
}