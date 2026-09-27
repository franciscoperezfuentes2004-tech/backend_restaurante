<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;

class SessionGuard
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($user = $request->user()) {
            try {
                if ($request->hasSession()) {
                    $session = $request->session();

                    $lastIp = $session->get('auth_session_ip');
                    $lastUa = $session->get('auth_session_ua');

                    $currentIp = $request->ip();
                    $currentUa = $request->header('User-Agent');

                    if ($lastUa && $lastUa !== $currentUa) {
                        AuditLogger::log(
                            'SESSION_ANOMALY',
                            'security',
                            "Cambio de User-Agent detectado durante la sesión del usuario. Previo: '{$lastUa}', Actual: '{$currentUa}'",
                            $user,
                            'warning'
                        );
                    }

                    if ($lastIp && $lastIp !== $currentIp) {
                        AuditLogger::log(
                            'SESSION_ANOMALY',
                            'security',
                            "Cambio de IP detectado durante la sesión del usuario. Previa: {$lastIp}, Actual: {$currentIp}",
                            $user,
                            'warning'
                        );
                    }

                    $session->put('auth_session_ip', $currentIp);
                    $session->put('auth_session_ua', $currentUa);
                }
            } catch (\Throwable $e) {
                // Session store not set on request - ignore for API stateless routes
            }
        }

        return $next($request);
    }
}
