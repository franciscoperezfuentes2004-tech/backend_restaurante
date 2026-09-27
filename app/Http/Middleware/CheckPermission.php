<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        if ($user->role === 'super_admin') {
            return $next($request);
        }

        if (!PermissionService::hasPermission($user, $permission)) {
            AuditLogger::log(
                'ACCESS_DENIED',
                'security',
                "Acceso denegado al intentar acceder con permiso requerido: {$permission}",
                $user,
                'warning'
            );
            return response()->json(['message' => 'No tienes permisos suficientes para realizar esta acción.'], 403);
        }

        return $next($request);
    }
}
