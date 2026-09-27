<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    public static function log(
        string $action,
        string $module,
        string $description,
        $user = null,
        string $severity = 'info'
    ): AuditLog {
        $req = request();
        $ipAddress = $req ? $req->ip() : '127.0.0.1';

        $authUser = ($user instanceof User) ? $user : (auth()->check() ? auth()->user() : null);

        $actionLower = strtolower($action);
        $accion = match ($actionLower) {
            'create', 'crear'                    => 'create',
            'update', 'actualizar', 'modificar' => 'update',
            'delete', 'eliminar'                => 'delete',
            'logout', 'cierre'                  => 'logout',
            default                             => 'login',
        };

        $cleanDescription = self::sanitizeDescription($description);

        $audit = AuditLog::create([
            'user_id'         => $authUser?->id,
            'user_name'       => $authUser?->name ?? 'Sistema',
            'role'            => $authUser?->role ?? 'sistema',
            'modulo'          => $module,
            'accion'          => $accion,
            'descripcion'     => $cleanDescription,
            'valores_antes'   => null,
            'valores_despues' => null,
            'ip_address'      => $ipAddress,
            'created_at'      => now(),
        ]);

        return $audit;
    }

    /**
     * Strips any potential sensitive keys or tokens from log strings
     */
    private static function sanitizeDescription(string $text): string
    {
        $patterns = [
            '/(password|contraseña|pwd|pass|secret|token|cookie|auth_token|bearer)\s*[:=]\s*[^\s,;]+/i' => '$1: [REDACTED]',
            '/bearer\s+[A-Za-z0-9\-\_\.\~\+\/]+=*/i' => 'Bearer [REDACTED]',
            '/\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|3[47][0-9]{13})\b/' => '[CREDIT_CARD_REDACTED]',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $text);
    }
}
