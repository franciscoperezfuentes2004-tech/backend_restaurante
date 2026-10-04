<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\RestaurantSetting;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserSession;
use App\Services\AuditLogger;
use App\Services\PermissionService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // Valid bcrypt hash generated for constant-time responses on non-existent users
    private const DUMMY_HASH = '$2y$12$e0MYzXyjpJS7Pd0RVvHwHe1w8r.1e7jVzC.S7V8w.1.1.1.1.1.1.';

    public function login(Request $request)
    {
        // 1. Sanitization of inputs
        $rawIdentifier = (string) ($request->input('email') ?? $request->input('username') ?? $request->input('login') ?? $request->input('identifier') ?? $request->input('phone') ?? '');
        $rawPassword   = (string) $request->input('password', '');

        $identifier = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $rawIdentifier));
        $password   = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $rawPassword));

        // 2. Validation
        $validator = validator(
            ['identifier' => $identifier, 'password' => $password],
            [
                'identifier' => 'required|string|max:255',
                'password'   => 'required|string|min:8|max:100',
            ],
            [
                'identifier.required' => 'Credenciales incorrectas.',
                'password.required'   => 'Credenciales incorrectas.',
            ]
        );

        if ($validator->fails()) {
            AuditLogger::log('LOGIN_FAILED', 'auth', "Intento de login con formato de datos inválido ({$identifier})", null, 'warning');
            return response()->json([
                'message' => 'Credenciales incorrectas.',
                'errors'  => ['credentials' => ['Credenciales incorrectas.']]
            ], 401);
        }

        $ip = $request->ip();
        $emailKey = strtolower($identifier);
        $deviceFingerprint = $request->cookie('aurum_device_fp') ?? $request->header('X-Device-Fingerprint') ?? md5($ip . $request->header('User-Agent'));

        // Keys for Rate Limiting
        $ipKey         = 'login_ip:' . $ip;
        $emailLimitKey = 'login_email:' . $emailKey;
        $email24hKey   = 'login_email_24h:' . $emailKey;
        $deviceKey     = 'login_device:' . $deviceFingerprint;

        // Check Rate Limits
        if (RateLimiter::tooManyAttempts($ipKey, 5)) {
            $seconds = RateLimiter::availableIn($ipKey);
            AuditLogger::log('LOGIN_BLOCKED', 'security', "Bloqueo por tasa de intentos excedida por IP ({$ip})", null, 'warning');
            return response()->json([
                'message'     => 'Demasiados intentos de acceso desde esta dirección IP. Intente más tarde.',
                'retry_after' => $seconds,
            ], 429);
        }

        if (RateLimiter::tooManyAttempts($emailLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($emailLimitKey);
            AuditLogger::log('LOGIN_BLOCKED', 'security', "Bloqueo por tasa de intentos excedida para identificador ({$identifier})", null, 'warning');
            return response()->json([
                'message'     => 'Cuenta bloqueada temporalmente por múltiples intentos fallidos.',
                'retry_after' => $seconds,
            ], 429);
        }

        if (RateLimiter::tooManyAttempts($email24hKey, 15)) {
            $seconds = RateLimiter::availableIn($email24hKey);
            AuditLogger::log('LOGIN_BLOCKED', 'security', "Bloqueo acumulativo de 24h para identificador ({$identifier})", null, 'critical');
            return response()->json([
                'message'     => 'Cuenta bloqueada temporalmente por seguridad debido a múltiples fallos acumulados.',
                'retry_after' => $seconds,
            ], 429);
        }

        if (RateLimiter::tooManyAttempts($deviceKey, 15)) {
            $seconds = RateLimiter::availableIn($deviceKey);
            AuditLogger::log('LOGIN_BLOCKED', 'security', "Bloqueo por tasa de intentos por Dispositivo ({$deviceFingerprint})", null, 'warning');
            return response()->json([
                'message'     => 'Demasiados intentos desde este dispositivo. Intente más tarde.',
                'retry_after' => $seconds,
            ], 429);
        }

        // 3. User lookup by email OR phone
        $user = User::where('email', strtolower($identifier))
            ->orWhere('phone', $identifier)
            ->first();

        if (!$user) {
            Hash::check($password, self::DUMMY_HASH);

            RateLimiter::hit($ipKey, 600);           // 10 mins
            RateLimiter::hit($emailLimitKey, 900);   // 15 mins
            RateLimiter::hit($email24hKey, 86400);   // 24 hrs
            RateLimiter::hit($deviceKey, 1800);      // 30 mins

            $attemptsLeft = max(0, 5 - RateLimiter::attempts($ipKey));

            AuditLogger::log('LOGIN_FAILED', 'auth', "Intento de inicio de sesión fallido para identificador inexistente: {$identifier}", null, 'warning');

            return response()->json([
                'message'       => 'Credenciales incorrectas.',
                'attempts_left' => $attemptsLeft,
            ], 401);
        }

        // Check if user is active
        if (isset($user->is_active) && !$user->is_active) {
            AuditLogger::log('LOGIN_BLOCKED', 'security', "Intento de login en cuenta inactiva para usuario ID {$user->id} ({$user->email})", $user, 'warning');
            return response()->json([
                'message' => 'Esta cuenta de usuario se encuentra inactiva.',
            ], 403);
        }

        // 4. Validate credentials
        if (!Hash::check($password, $user->password)) {
            RateLimiter::hit($ipKey, 600);
            RateLimiter::hit($emailLimitKey, 900);
            RateLimiter::hit($email24hKey, 86400);
            RateLimiter::hit($deviceKey, 1800);

            $attemptsLeft = max(0, 5 - RateLimiter::attempts($ipKey));

            AuditLogger::log('LOGIN_FAILED', 'auth', "Contraseña incorrecta para el usuario ID {$user->id} ({$identifier})", $user, 'warning');
            $identificadorEnmascarado = str_contains($identifier, '@') ? NotificationService::maskEmail($identifier) : $identifier;
            NotificationService::create('login_failed', 'Intento de Login Fallido', "Intento de inicio de sesión fallido para {$identificadorEnmascarado} desde la IP {$ip}", ['email' => $identificadorEnmascarado, 'ip' => $ip], $user->id);

            return response()->json([
                'message'       => 'Credenciales incorrectas.',
                'attempts_left' => $attemptsLeft,
            ], 401);
        }

        // 5. Successful Login: Clear Rate Limiting hits
        RateLimiter::clear($ipKey);
        RateLimiter::clear($emailLimitKey);
        RateLimiter::clear($email24hKey);
        RateLimiter::clear($deviceKey);

        Auth::login($user);
        $user->last_login_at = now();
        $user->save();

        // Regenerate Session ID to prevent Session Fixation
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $sessionId = $request->hasSession() ? $request->session()->getId() : Str::uuid()->toString();

        // Register in user_sessions table with last_ip and last_seen_at
        UserSession::create([
            'user_id'       => $user->id,
            'session_id'    => $sessionId,
            'ip_address'    => $ip,
            'last_ip'       => $ip,
            'user_agent'    => Str::limit($request->header('User-Agent') ?? '', 1000, ''),
            'browser'       => $this->detectBrowser($request->header('User-Agent')),
            'platform'      => $this->detectPlatform($request->header('User-Agent')),
            'last_activity' => now(),
            'last_seen_at'  => now(),
            'created_at'    => now(),
        ]);

        AuditLogger::log('LOGIN_SUCCESS', 'Autenticación', "Inicio de sesión exitoso para usuario {$user->name} ({$user->email})", $user, 'info');
        AuditLog::create([
            'user_id'         => $user->id,
            'user_name'       => $user->name,
            'role'            => $user->role,
            'modulo'          => 'Autenticación',
            'accion'          => 'login',
            'descripcion'     => "Inicio de sesión del usuario {$user->name}",
            'valores_antes'   => null,
            'valores_despues' => null,
            'ip_address'      => $ip,
            'created_at'      => now(),
        ]);
        $emailEnmascarado = NotificationService::maskEmail($user->email);
        NotificationService::create('login_success', 'Inicio de Sesión', "Inicio de sesión: {$emailEnmascarado}", ['user_id' => $user->id, 'ip' => $ip], $user->id);

        // Validación y envío del reporte financiero diario automático al primer login del día
        $this->checkAndSendDailyFinancialReport();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token'        => $token,
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'phone'       => $user->phone,
                'role'        => $user->role,
                'roles'       => [$user->role],
                'branch_id'   => $user->branch_id ?? 1,
                'branch_name' => $user->branch_name ?? 'Sucursal Centro',
                'permissions' => PermissionService::getPermissions($user->role),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $sessionId = null;
        try {
            if ($request->hasSession()) {
                $sessionId = $request->session()->getId();
            }
        } catch (\Throwable $e) {}

        if ($user) {
            AuditLogger::log('LOGOUT', 'Autenticación', "Cierre de sesión del usuario {$user->email}", $user, 'info');
            AuditLog::create([
                'user_id'         => $user->id,
                'user_name'       => $user->name,
                'role'            => $user->role,
                'modulo'          => 'Autenticación',
                'accion'          => 'logout',
                'descripcion'     => "Cierre de sesión del usuario {$user->name}",
                'valores_antes'   => null,
                'valores_despues' => null,
                'ip_address'      => $request->ip() ?? '127.0.0.1',
                'created_at'      => now(),
            ]);

            if ($user->currentAccessToken() instanceof \Laravel\Sanctum\PersonalAccessToken) {
                $user->currentAccessToken()->delete();
            }

            if ($sessionId) {
                UserSession::where('user_id', $user->id)
                    ->where('session_id', $sessionId)
                    ->update(['ended_at' => now()]);
            }
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        try {
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        } catch (\Throwable $e) {}

        return response()->json(['message' => 'Sesión cerrada correctamente.'], 200);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $ip = $request->ip();

        // Update last_activity, last_seen_at and last_ip on user session
        if ($request->hasSession()) {
            UserSession::where('session_id', $request->session()->getId())
                ->update([
                    'last_activity' => now(),
                    'last_seen_at'  => now(),
                    'last_ip'       => $ip,
                ]);
        }

        return response()->json([
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'role'        => $user->role,
            'roles'       => [$user->role],
            'branch_id'   => $user->branch_id ?? 1,
            'branch_name' => $user->branch_name ?? 'Sucursal Centro',
            'permissions' => PermissionService::getPermissions($user->role),
        ]);
    }

    public function activeSessions(Request $request)
    {
        $user = $request->user();
        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;

        $sessions = UserSession::where('user_id', $user->id)
            ->whereNull('ended_at')
            ->orderBy('last_seen_at', 'desc')
            ->get()
            ->map(function ($s) use ($currentSessionId) {
                return [
                    'id'            => $s->id,
                    'session_id'    => $s->session_id,
                    'ip_address'    => $s->last_ip ?? $s->ip_address,
                    'browser'       => $s->browser,
                    'platform'      => $s->platform,
                    'last_seen_at'  => $s->last_seen_at ? $s->last_seen_at->diffForHumans() : $s->last_activity->diffForHumans(),
                    'is_current'    => $s->session_id === $currentSessionId,
                ];
            });

        return response()->json($sessions);
    }

    public function revokeSession(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $user = $request->user();
        $targetSessionId = $request->input('session_id');

        UserSession::where('user_id', $user->id)
            ->where('session_id', $targetSessionId)
            ->update(['ended_at' => now()]);

        if (config('session.driver') === 'database') {
            try {
                DB::table('sessions')->where('id', $targetSessionId)->delete();
            } catch (\Throwable $e) {}
        }

        AuditLogger::log('SESSION_REVOKED', 'auth', "Sesión de usuario revocada remotamente: {$targetSessionId}", $user, 'info');

        return response()->json(['message' => 'Sesión revocada correctamente.']);
    }

    public function confirmPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->input('password'), $user->password)) {
            AuditLogger::log('PASSWORD_CONFIRM_FAILED', 'security', "Fallo de confirmación de contraseña para usuario {$user->email}", $user, 'warning');
            return response()->json(['message' => 'Contraseña incorrecta.'], 422);
        }

        if ($request->hasSession()) {
            // Confirm password is valid for 5 minutes (300 seconds)
            $request->session()->put('auth_password_confirmed_at', time());
        }

        AuditLogger::log('PASSWORD_CONFIRMED', 'security', "Confirmación de contraseña exitosa para usuario {$user->email}", $user, 'info');

        return response()->json(['confirmed' => true, 'expires_in_seconds' => 300]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:12',
        ]);

        $user = $request->user();

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta.'], 422);
        }

        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;

        // Invalidate all OTHER sessions for this user on password change
        UserSession::where('user_id', $user->id)
            ->where('session_id', '!=', $currentSessionId)
            ->update(['ended_at' => now()]);

        if (config('session.driver') === 'database' && $currentSessionId) {
            try {
                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $currentSessionId)
                    ->delete();
            } catch (\Throwable $e) {}
        }

        // Regenerate current session ID after password change
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        AuditLogger::log('PASSWORD_CHANGED', 'auth', "Contraseña cambiada exitosamente para usuario {$user->email}. Se cerraron las demás sesiones activas.", $user, 'info');

        return response()->json(['message' => 'Contraseña actualizada correctamente. Las demás sesiones fueron cerradas.']);
    }

    private function detectBrowser(?string $ua): string
    {
        if (!$ua) return 'Desconocido';
        if (str_contains($ua, 'Edg')) return 'Edge';
        if (str_contains($ua, 'Chrome')) return 'Chrome';
        if (str_contains($ua, 'Firefox')) return 'Firefox';
        if (str_contains($ua, 'Safari')) return 'Safari';
        return 'Navegador';
    }

    private function detectPlatform(?string $ua): string
    {
        if (!$ua) return 'Desconocido';
        if (str_contains($ua, 'Windows')) return 'Windows';
        if (str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS')) return 'macOS';
        if (str_contains($ua, 'Linux')) return 'Linux';
        if (str_contains($ua, 'Android')) return 'Android';
        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) return 'iOS';
        return 'Sistema Operativo';
    }

    /**
     * Valida y dispara el reporte financiero diario automático al primer login del día.
     */
    protected function checkAndSendDailyFinancialReport(): void
    {
        try {
            $settings = RestaurantSetting::first();
            if (!$settings) {
                return;
            }

            $nowMexico = Carbon::now('America/Mexico_City');

            // Verificar si last_financial_report_date es hoy o es diferente/nulo
            $isToday = false;
            if (!empty($settings->last_financial_report_date)) {
                $lastReportDate = Carbon::parse($settings->last_financial_report_date, 'America/Mexico_City');
                $isToday = $lastReportDate->isSameDay($nowMexico);
            }

            if (!$isToday) {
                try {
                    $yesterdayStart = Carbon::yesterday('America/Mexico_City')->startOfDay();
                    $yesterdayEnd   = Carbon::yesterday('America/Mexico_City')->endOfDay();

                    // 1. Total ventas (órdenes pagadas o completadas creadas en el día de ayer)
                    $paidOrders = Order::whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
                        ->where(function ($q) {
                            $q->where('payment_status', 'paid')
                              ->orWhere('status', 'completed')
                              ->orWhere('status', 'entregado');
                        });

                    $totalVentas  = round((float) $paidOrders->sum('total_amount'), 2);
                    $totalOrdenes = (int) $paidOrders->count();

                    // 2. Total gastos (entradas de inventario y mermas creadas en el día de ayer)
                    $costoEntradas = (float) StockMovement::where('type', 'entrada')
                        ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
                        ->sum(DB::raw('quantity * COALESCE(cost_per_unit, 0)'));

                    $costoMermas = (float) StockMovement::where('type', 'merma')
                        ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
                        ->sum(DB::raw('quantity * COALESCE(cost_per_unit, 0)'));

                    $totalGastos = round($costoEntradas + $costoMermas, 2);
                    $balance     = round($totalVentas - $totalGastos, 2);

                    $datos = [
                        'fecha'         => Carbon::yesterday('America/Mexico_City')->format('d/m/Y'),
                        'total_ventas'  => $totalVentas,
                        'total_gastos'  => $totalGastos,
                        'balance'       => $balance,
                        'total_ordenes' => $totalOrdenes,
                    ];

                    app(NotificationService::class)->sendDailyFinancialReport($datos);

                    // Actualizar last_financial_report_date a la fecha de hoy y guardar
                    $settings->last_financial_report_date = $nowMexico->toDateString();
                    $settings->save();
                } catch (\Throwable $reportEx) {
                    Log::error("Error procesando reporte financiero diario en login: " . $reportEx->getMessage(), [
                        'trace' => $reportEx->getTraceAsString(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error("Error en validación de reporte financiero diario: " . $e->getMessage());
        }
    }
}
