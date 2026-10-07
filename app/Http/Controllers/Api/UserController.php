<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Map roles to numeric rank levels for strict hierarchy enforcement.
     * Order: super_admin (5) > admin (4) > gerente (3) > mesero = cocina = repartidor = cajero (1).
     */
    private const ROLE_RANKS = [
        'super_admin'   => 5,
        'superadmin'    => 5,
        'admin'         => 4,
        'administrador' => 4,
        'gerente'       => 3,
        'cajero'        => 1,
        'mesero'        => 1,
        'cocina'        => 1,
        'repartidor'    => 1,
        'chef'          => 1,
        'almacenista'   => 1,
    ];

    /**
     * Helper to get role rank. Default 1 for unlisted roles.
     */
    private function getRoleRank(string $role): int
    {
        $norm = strtolower(trim($role));
        return self::ROLE_RANKS[$norm] ?? 1;
    }

    /**
     * Apply strict hierarchy scoping to a User query for the authenticated user.
     * Rules:
     * 1. Excludes the authenticated user themselves (id != authUser->id).
     * 2. Excludes all users with equal or higher role rank than authUser ($targetRank >= $authRank).
     * 3. Only includes users strictly below authUser's rank ($targetRank < $authRank).
     */
    private function scopeHierarchy($query, User $authUser)
    {
        $authRank = $this->getRoleRank($authUser->role);

        // Exclude authenticated user themselves
        $query->where('id', '!=', $authUser->id);

        // Only include users with role rank strictly lower than authUser's rank
        $allowedRoles = array_keys(array_filter(self::ROLE_RANKS, function ($rank) use ($authRank) {
            return $rank < $authRank;
        }));

        return $query->whereIn('role', $allowedRoles);
    }

    /**
     * Check if authUser can manage (view/edit/toggle/delete) a target user.
     * Must be strictly lower rank AND not self.
     */
    private function canManageUser(User $authUser, User $targetUser): bool
    {
        if ($authUser->id === $targetUser->id) {
            return false;
        }

        $authRank = $this->getRoleRank($authUser->role);
        $targetRank = $this->getRoleRank($targetUser->role);

        return $targetRank < $authRank;
    }

    /**
     * GET /api/admin/usuarios
     * Lista paginada de usuarios filtrada por jerarquía, búsqueda y rol.
     */
    public function index(Request $request)
    {
        $authUser = $request->user();
        $query = User::query();

        if ($authUser) {
            $this->scopeHierarchy($query, $authUser);
        }

        // Search by name, phone or email
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by role (standard parameter: role)
        if ($request->filled('role') && $request->role !== 'all' && $request->role !== 'Todos los Roles' && $request->role !== 'Todos') {
            $query->where('role', $request->role);
        }

        $perPage = (int) $request->input('per_page', 15);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $customUserIds = UserPermission::distinct()->pluck('user_id')->toArray();

        // Map user data cleanly
        $users->getCollection()->transform(function ($u) use ($customUserIds) {
            $hasCustom = in_array($u->id, $customUserIds, true);
            return [
                'id'                            => $u->id,
                'name'                          => $u->name,
                'nombre'                        => $u->name,
                'email'                         => $u->email,
                'correo'                        => $u->email,
                'phone'                         => $u->phone ?? '',
                'role'                          => $u->role,
                'rol'                           => $u->role,
                'roleId'                        => $u->role,
                'is_active'                     => (bool) ($u->is_active ?? true),
                'status'                        => ($u->is_active ?? true) ? 'activo' : 'inactivo',
                'has_custom_permissions'        => $hasCustom,
                'tiene_permisos_personalizados' => $hasCustom,
                'created_at'                    => $u->created_at ? $u->created_at->format('Y-m-d H:i:s') : null,
                'last_login'                    => $u->last_login_at ? $u->last_login_at->diffForHumans() : 'Nunca',
                'last_login_at'                 => $u->last_login_at ? $u->last_login_at->format('Y-m-d H:i:s') : null,
            ];
        });

        return response()->json($users);
    }

    /**
     * GET /api/admin/usuarios/stats
     * Estadísticas de tarjetas de métricas alimentadas por la jerarquía.
     */
    public function stats(Request $request)
    {
        $authUser = $request->user();
        $query = User::query();

        if ($authUser) {
            $this->scopeHierarchy($query, $authUser);
        }

        $totalCuentas = (clone $query)->count();
        $activos      = (clone $query)->where(function ($q) {
            $q->where('is_active', true)
              ->orWhere('is_active', 1)
              ->orWhereNull('is_active');
        })->count();
        $inactivos    = (clone $query)->where(function ($q) {
            $q->where('is_active', false)
              ->orWhere('is_active', 0);
        })->count();

        return response()->json([
            'total_cuentas'     => $totalCuentas,
            'activos'           => $activos,
            'usuarios_activos'  => $activos,
            'inactivos'         => $inactivos,
            'cuentas_inactivas' => $inactivos,
        ]);
    }

    /**
     * POST /api/admin/usuarios
     * Crear usuario validando unicidad y jerarquía de rol.
     */
    public function store(StoreUserRequest $request)
    {
        $authUser = $request->user();
        $validated = $request->validated();

        // Hierarchy check for new user role assignment
        $authRank   = $this->getRoleRank($authUser->role);
        $targetRank = $this->getRoleRank($validated['role']);

        if (!$authUser->isSuperAdmin() && $targetRank >= $authRank) {
            return response()->json([
                'message' => 'No tiene permisos para crear usuarios con un rol igual o superior al suyo.'
            ], 403);
        }

        $password = !empty($validated['password'])
            ? Hash::make($validated['password'])
            : Hash::make('Temporal123!');

        $user = User::create([
            'name'        => trim($validated['name']),
            'phone'       => trim($validated['phone']),
            'email'       => strtolower(trim($validated['email'])),
            'role'        => $validated['role'],
            'password'    => $password,
            'is_active'   => $validated['is_active'] ?? true,
            'branch_id'   => $validated['branch_id'] ?? ($authUser->branch_id ?? 1),
            'branch_name' => $validated['branch_name'] ?? ($authUser->branch_name ?? 'Sucursal Centro'),
        ]);

        AuditLogger::log('USER_CREATED', 'Usuarios', "Usuario '{$user->name}' ({$user->email}) creado con rol '{$user->role}' por {$authUser->name}", $authUser, 'info');

        return response()->json([
            'message' => "Usuario '{$user->name}' registrado correctamente.",
            'user'    => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'role'          => $user->role,
                'is_active'     => true,
                'status'        => 'activo',
                'created_at'    => $user->created_at->format('Y-m-d H:i:s'),
                'last_login'    => 'Nunca',
            ]
        ], 201);
    }

    /**
     * GET /api/admin/usuarios/{id}
     * Devuelve datos básicos del usuario con verificación de jerarquía.
     */
    public function show(Request $request, $id)
    {
        $authUser = $request->user();
        $targetUser = User::findOrFail($id);

        if (!$this->canManageUser($authUser, $targetUser)) {
            return response()->json(['message' => 'No tiene permisos para ver este usuario.'], 403);
        }

        return response()->json([
            'id'            => $targetUser->id,
            'name'          => $targetUser->name,
            'email'         => $targetUser->email,
            'phone'         => $targetUser->phone ?? '',
            'role'          => $targetUser->role,
            'roleId'        => $targetUser->role,
            'is_active'     => (bool) ($targetUser->is_active ?? true),
            'status'        => ($targetUser->is_active ?? true) ? 'activo' : 'inactivo',
            'created_at'    => $targetUser->created_at ? $targetUser->created_at->format('Y-m-d H:i:s') : null,
            'last_login'    => $targetUser->last_login_at ? $targetUser->last_login_at->diffForHumans() : 'Nunca',
            'last_login_at' => $targetUser->last_login_at ? $targetUser->last_login_at->format('Y-m-d H:i:s') : null,
        ]);
    }

    /**
     * PUT /api/admin/usuarios/{id}
     * Editar usuario con restricción de jerarquía.
     */
    public function update(UpdateUserRequest $request, $id)
    {
        $authUser = $request->user();
        $targetUser = User::findOrFail($id);

        if (!$this->canManageUser($authUser, $targetUser)) {
            return response()->json(['message' => 'No tiene permisos para editar usuarios de igual o mayor rango.'], 403);
        }

        $validated = $request->validated();

        // New role rank hierarchy check
        if (isset($validated['role'])) {
            $authRank   = $this->getRoleRank($authUser->role);
            $newTargetRank = $this->getRoleRank($validated['role']);

            if (!$authUser->isSuperAdmin() && $newTargetRank >= $authRank) {
                return response()->json([
                    'message' => 'No puede asignar un rol igual o superior al suyo.'
                ], 403);
            }
        }

        $updateData = [];
        if (isset($validated['name'])) {
            $updateData['name'] = trim($validated['name']);
        }
        if (isset($validated['phone'])) {
            $updateData['phone'] = trim($validated['phone']);
        }
        if (isset($validated['email'])) {
            $updateData['email'] = strtolower(trim($validated['email']));
        }
        if (isset($validated['role'])) {
            $updateData['role'] = $validated['role'];
        }
        if (isset($validated['is_active'])) {
            $updateData['is_active'] = (bool) $validated['is_active'];
        }

        $targetUser->update($updateData);

        AuditLogger::log('USER_UPDATED', 'Usuarios', "Usuario '{$targetUser->name}' actualizado por {$authUser->name}", $authUser, 'info');

        return response()->json([
            'message' => "Usuario '{$targetUser->name}' actualizado correctamente.",
            'user'    => [
                'id'            => $targetUser->id,
                'name'          => $targetUser->name,
                'email'         => $targetUser->email,
                'phone'         => $targetUser->phone,
                'role'          => $targetUser->role,
                'is_active'     => (bool) ($targetUser->is_active ?? true),
                'status'        => ($targetUser->is_active ?? true) ? 'activo' : 'inactivo',
                'created_at'    => $targetUser->created_at->format('Y-m-d H:i:s'),
                'last_login'    => $targetUser->last_login_at ? $targetUser->last_login_at->diffForHumans() : 'Nunca',
            ]
        ]);
    }

    /**
     * PATCH /api/admin/usuarios/{id}/toggle
     * Activa o desactiva el usuario con restricción de jerarquía.
     */
    public function toggle(Request $request, $id)
    {
        $authUser = $request->user();
        $targetUser = User::findOrFail($id);

        if ($targetUser->id === $authUser->id) {
            return response()->json(['message' => 'No puede cambiar su propio estado de cuenta.'], 403);
        }

        if (!$this->canManageUser($authUser, $targetUser)) {
            return response()->json(['message' => 'No tiene permisos para modificar el estado de este usuario.'], 403);
        }

        $targetUser->is_active = !($targetUser->is_active ?? true);
        $targetUser->save();

        $statusText = $targetUser->is_active ? 'activado' : 'desactivado';
        AuditLogger::log('USER_TOGGLED', 'Usuarios', "Usuario '{$targetUser->name}' fue {$statusText} por {$authUser->name}", $authUser, 'info');

        return response()->json([
            'message'   => "Cuenta de usuario '{$targetUser->name}' {$statusText} correctamente.",
            'is_active' => (bool) $targetUser->is_active,
            'status'    => $targetUser->is_active ? 'activo' : 'inactivo'
        ]);
    }

    /**
     * DELETE /api/admin/usuarios/{id}
     * Eliminación permanente de usuario con restricción de jerarquía.
     */
    public function destroy(Request $request, $id)
    {
        $authUser = $request->user();
        $targetUser = User::findOrFail($id);

        if ($targetUser->id === $authUser->id) {
            return response()->json(['message' => 'No se puede eliminar a uno mismo.'], 403);
        }

        if (!$this->canManageUser($authUser, $targetUser)) {
            return response()->json(['message' => 'No tiene permisos para eliminar usuarios de igual o mayor rango.'], 403);
        }

        $userName = $targetUser->name;
        $targetUser->forceDelete();

        AuditLogger::log('USER_DELETED', 'Usuarios', "Usuario '{$userName}' fue eliminado por {$authUser->name}", $authUser, 'warning');

        return response()->json(['message' => "Usuario '{$userName}' eliminado de la plataforma."]);
    }

    /**
     * PATCH /api/admin/usuarios/{id}/reset-password
     * Dispara el flujo autónomo enviando el código OTP de verificación al correo del usuario.
     */
    public function resetPassword(Request $request, $id)
    {
        $authUser = $request->user();

        if (!$authUser->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json(['message' => 'Solo los administradores pueden gestionar restablecimientos de contraseñas.'], 403);
        }

        $targetUser = User::findOrFail($id);

        AuditLogger::log('PASSWORD_RESET_TRIGGERED', 'Usuarios', "Solicitud de restablecimiento autónomo para usuario '{$targetUser->name}' ({$targetUser->email}) disparada por {$authUser->name}", $authUser, 'info');

        return app(\App\Http\Controllers\PasswordResetController::class)->forgotPassword(new Request(['email' => $targetUser->email]));
    }
}
