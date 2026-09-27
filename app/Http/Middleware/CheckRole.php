<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        if ($user->role === 'super_admin') {
            return $next($request);
        }

        if (!$user->hasAnyRole($roles)) {
            AuditLogger::log(
                'ACCESS_DENIED',
                'security',
                "Acceso denegado al intentar acceder con rol '{$user->role}'. Roles requeridos: " . implode(', ', $roles),
                $user,
                'warning'
            );
            return response()->json(['message' => 'No tienes autorización para acceder a esta sección.'], 403);
        }

        return $next($request);
    }
}
