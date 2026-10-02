<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PasswordResetController extends Controller
{
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
            $webhookUrl = env('N8N_WEBHOOK_PASSWORD') ?: ($n8nBaseUrl . '/webhook/recuperar-password');

            $response = Http::post($webhookUrl, [
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