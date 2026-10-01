<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class PasswordResetController extends Controller
{
    public function generateTempPassword(Request $request)
    {
        // 1. Validar que nos envíen un correo válido y que exista en la base de datos
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->first();

        // 2. Generar contraseña aleatoria de 8 caracteres
        $tempPassword = Str::random(8);

        // 3. Guardar la nueva contraseña encriptada y encender la bandera
        $user->update([
            'password' => Hash::make($tempPassword),
            'must_change_password' => true
        ]);

        // 4. Disparar el webhook para enviar la contraseña temporal
        Http::post(env('N8N_WEBHOOK_PASSWORD'), [
            'telefono' => $user->phone,
            'password_temporal' => $tempPassword
        ]);

        return response()->json([
            'message' => 'Contraseña temporal generada con éxito.',
            // OJO: Te devuelvo la contraseña en la respuesta SOLO PARA TUS PRUEBAS LOCALES.
            // Cuando n8n esté funcionando, borraremos esta línea por seguridad.
            'temp_password_debug' => $tempPassword 
        ]);
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