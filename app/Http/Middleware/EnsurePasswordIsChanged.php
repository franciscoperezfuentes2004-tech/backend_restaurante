<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        // Si el usuario está autenticado y tiene la bandera encendida...
        if ($request->user() && $request->user()->must_change_password) {
            return response()->json([
                'error' => 'Por seguridad, debes cambiar tu contraseña temporal para continuar.',
                'code' => 'MUST_CHANGE_PASSWORD'
            ], 403);
        }

        // Si todo está normal, lo deja pasar al sistema
        return $next($request);
    }
}