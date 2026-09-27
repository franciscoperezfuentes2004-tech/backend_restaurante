<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    private const CLAVE_MAP = [
        'view_categories'     => 'ver_categorias',
        'manage_categories'   => 'gestionar_categorias',
        'view_dishes'         => 'ver_platillos',
        'manage_dishes'       => 'gestionar_platillos',
        'manage_extras'       => 'gestionar_extras',
        'view_orders'         => 'ver_pedidos',
        'view_reservations'   => 'ver_reservaciones',
        'manage_reservations' => 'gestionar_reservaciones',
        'view_delivery'       => 'ver_delivery',
        'manage_promotions'   => 'gestionar_promociones',
        'view_reviews'        => 'ver_resenas',
        'respond_reviews'     => 'responder_resenas',
        'view_inventory'      => 'ver_inventario',
        'manage_inventory'    => 'gestionar_inventario',
        'manage_suppliers'    => 'gestionar_proveedores',
        'view_dashboard'      => 'ver_dashboard',
        'view_reports'        => 'ver_reportes',
        'view_logs'           => 'ver_bitacora',
        'manage_settings'     => 'configuracion',
    ];

    private const ROLE_ALIAS_MAP = [
        'manager' => 'gerente',
        'waiter'  => 'mesero',
        'kitchen' => 'cocina',
        'driver'  => 'repartidor',
    ];

    private function normalizeRole(string $role): string
    {
        $r = strtolower(trim($role));
        return self::ROLE_ALIAS_MAP[$r] ?? $r;
    }

    private function normalizeClave(string $clave): string
    {
        $c = strtolower(trim($clave));
        return self::CLAVE_MAP[$c] ?? $c;
    }

    /**
     * GET /api/admin/permisos/{rol}
     * Devuelve los 19 permisos con su estado activo/inactivo para ese rol.
     */
    public function getRolePermissions(Request $request, $rol)
    {
        $role = $this->normalizeRole($rol);
        $allPermissions = Permission::orderBy('id')->get();

        $activeMap = RolePermission::where('role', $role)
            ->orWhere('role', $rol)
            ->pluck('activo', 'permission_id')
            ->toArray();

        $result = $allPermissions->map(function ($p) use ($activeMap, $role) {
            // Super Admin and Admin always have all permissions active
            $isActive = in_array($role, ['super_admin', 'admin'], true)
                ? true
                : (bool) ($activeMap[$p->id] ?? false);

            return [
                'id'          => $p->id,
                'clave'       => $p->clave,
                'clave_es'    => $p->clave,
                'nombre'      => $p->nombre,
                'label'       => $p->nombre,
                'descripcion' => $p->descripcion,
                'desc'        => $p->descripcion,
                'modulo'      => $p->modulo,
                'group'       => $p->modulo,
                'activo'      => $isActive,
            ];
        });

        return response()->json([
            'role'       => $rol,
            'role_norm'  => $role,
            'permisos'   => $result,
            'total'      => $result->count(),
        ]);
    }

    /**
     * PUT /api/admin/permisos/{rol}
     * Guarda los permisos base del rol completo.
     */
    public function updateRolePermissions(Request $request, $rol)
    {
        $authUser = $request->user();

        if (!$authUser->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json(['message' => 'No tiene permisos para modificar la matriz de roles.'], 403);
        }

        $role = $this->normalizeRole($rol);

        if (in_array($role, ['super_admin', 'admin'], true)) {
            return response()->json(['message' => 'Los permisos del rol Administrador son globales y no modificables.'], 422);
        }

        $input = $request->input('permisos', $request->input('permissions', []));
        $allPermissions = Permission::all();

        $activePermissionIds = [];

        if (is_array($input)) {
            // If associative object mapping keys to boolean e.g. {"ver_categorias": true, "1": true}
            if (array_keys($input) !== range(0, count($input) - 1)) {
                foreach ($input as $key => $val) {
                    if ($val) {
                        $normKey = $this->normalizeClave((string) $key);
                        $perm = $allPermissions->first(fn($p) => $p->clave === $normKey || (string)$p->id === (string)$key);
                        if ($perm) {
                            $activePermissionIds[] = $perm->id;
                        }
                    }
                }
            } else {
                // List of active keys/IDs
                foreach ($input as $item) {
                    $normKey = $this->normalizeClave((string) $item);
                    $perm = $allPermissions->first(fn($p) => $p->clave === $normKey || (string)$p->id === (string)$item);
                    if ($perm) {
                        $activePermissionIds[] = $perm->id;
                    }
                }
            }
        }

        foreach ($allPermissions as $p) {
            $isActive = in_array($p->id, $activePermissionIds, true);
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => $p->id],
                ['activo' => $isActive]
            );
            // Sync alias if role has an alias e.g. manager <-> gerente
            if ($rol !== $role) {
                RolePermission::updateOrCreate(
                    ['role' => $rol, 'permission_id' => $p->id],
                    ['activo' => $isActive]
                );
            }
        }

        AuditLogger::log('ROLE_PERMISSIONS_UPDATED', 'Permisos', "Permisos base del rol '{$rol}' actualizados por {$authUser->name}", $authUser, 'info');

        return response()->json([
            'message'  => "Permisos base del rol '{$rol}' actualizados correctamente.",
            'role'     => $rol,
        ]);
    }

    /**
     * GET /api/admin/permisos/{rol}/usuarios
     * Lista de usuarios del rol con indicador de si tienen permisos personalizados.
     * Restringido: solo devuelve usuarios cuando el rol sea admin o gerente.
     */
    public function getRoleUsers(Request $request, $rol)
    {
        $role = $this->normalizeRole($rol);

        if (!in_array($role, ['admin', 'administrador', 'super_admin', 'superadmin', 'gerente', 'manager'], true)) {
            return response()->json([
                'role'     => $rol,
                'usuarios' => [],
                'total'    => 0,
            ]);
        }

        $users = User::where(function ($q) use ($role, $rol) {
            $q->where('role', $role)->orWhere('role', $rol);
        })->get();

        $customUserIds = UserPermission::distinct()->pluck('user_id')->toArray();

        $result = $users->map(function ($u) use ($customUserIds) {
            $userPermsCount = UserPermission::where('user_id', $u->id)->count();
            $hasCustom = in_array($u->id, $customUserIds, true) && $userPermsCount > 0;

            return [
                'id'                            => $u->id,
                'name'                          => $u->name,
                'email'                         => $u->email,
                'phone'                         => $u->phone ?? '',
                'role'                          => $u->role,
                'has_custom_permissions'        => $hasCustom,
                'tiene_permisos_personalizados' => $hasCustom,
                'custom_permissions_count'      => $userPermsCount,
                'extraPermissions'              => UserPermission::where('user_id', $u->id)
                    ->where('activo', true)
                    ->with('permission')
                    ->get()
                    ->pluck('permission.clave')
                    ->toArray(),
            ];
        });

        return response()->json([
            'role'     => $rol,
            'usuarios' => $result,
            'total'    => $result->count(),
        ]);
    }

    /**
     * GET /api/admin/permisos/usuario/{id}
     * Devuelve permisos del rol base + permisos extra del usuario.
     */
    public function getUserPermissions(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $role = $this->normalizeRole($user->role);

        $allPermissions = Permission::orderBy('id')->get();

        $roleActiveMap = RolePermission::where('role', $role)
            ->pluck('activo', 'permission_id')
            ->toArray();

        $userOverrideMap = UserPermission::where('user_id', $user->id)
            ->pluck('activo', 'permission_id')
            ->toArray();

        $hasCustom = count($userOverrideMap) > 0;

        $permisos = $allPermissions->map(function ($p) use ($roleActiveMap, $userOverrideMap, $role) {
            $roleActive = in_array($role, ['super_admin', 'admin'], true)
                ? true
                : (bool) ($roleActiveMap[$p->id] ?? false);

            $userOverride = isset($userOverrideMap[$p->id]) ? (bool) $userOverrideMap[$p->id] : null;

            // Final effective permission: user override if present, else role default
            $effectiveActive = $userOverride !== null ? $userOverride : $roleActive;

            return [
                'id'                     => $p->id,
                'clave'                  => $p->clave,
                'nombre'                 => $p->nombre,
                'descripcion'            => $p->descripcion,
                'modulo'                 => $p->modulo,
                'role_activo'            => $roleActive,
                'user_override'          => $userOverride,
                'activo'                 => $effectiveActive,
                'tiene_excepcion'        => $userOverride !== null,
            ];
        });

        return response()->json([
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
            'has_custom_permissions'        => $hasCustom,
            'tiene_permisos_personalizados' => $hasCustom,
            'permisos'                       => $permisos,
        ]);
    }

    /**
     * PUT /api/admin/permisos/usuario/{id}
     * Guarda los permisos extra del usuario (excepciones individuales).
     * Jerarquía de Modificación:
     * - Super Admin → puede modificar Admin y Gerente
     * - Admin → puede modificar SOLO Gerente (403 si target es Admin u otro rol)
     * - Cualquier otro rol → 403 inmediato
     */
    public function updateUserPermissions(Request $request, $id)
    {
        $authUser = $request->user();
        $targetUser = User::findOrFail($id);

        $authRole   = $this->normalizeRole($authUser->role ?? '');
        $targetRole = $this->normalizeRole($targetUser->role ?? '');

        if (in_array($authRole, ['super_admin', 'superadmin'], true)) {
            if (!in_array($targetRole, ['admin', 'administrador', 'gerente', 'manager'], true)) {
                return response()->json([
                    'message' => 'El Super Admin solo puede modificar permisos de usuarios Admin y Gerente.'
                ], 403);
            }
        } elseif (in_array($authRole, ['admin', 'administrador'], true)) {
            if (in_array($targetRole, ['admin', 'administrador', 'super_admin', 'superadmin'], true)) {
                return response()->json([
                    'message' => 'Un Administrador no puede modificar los permisos de otro Administrador ni Super Admin.'
                ], 403);
            }
            if ($targetRole !== 'gerente' && $targetRole !== 'manager') {
                return response()->json([
                    'message' => 'Un Administrador solo puede modificar permisos de usuarios Gerente.'
                ], 403);
            }
        } else {
            return response()->json([
                'message' => 'No tiene permisos jerárquicos para modificar permisos de usuarios.'
            ], 403);
        }
        $input = $request->input('permisos', $request->input('permissions', $request->input('extraPermissions', [])));

        $allPermissions = Permission::all();
        $activePermissionIds = [];

        if (is_array($input)) {
            if (array_keys($input) !== range(0, count($input) - 1)) {
                foreach ($input as $key => $val) {
                    if ($val) {
                        $normKey = $this->normalizeClave((string) $key);
                        $perm = $allPermissions->first(fn($p) => $p->clave === $normKey || (string)$p->id === (string)$key);
                        if ($perm) {
                            $activePermissionIds[] = $perm->id;
                        }
                    }
                }
            } else {
                foreach ($input as $item) {
                    $normKey = $this->normalizeClave((string) $item);
                    $perm = $allPermissions->first(fn($p) => $p->clave === $normKey || (string)$p->id === (string)$item);
                    if ($perm) {
                        $activePermissionIds[] = $perm->id;
                    }
                }
            }
        }

        // Clean previous user overrides and set new ones
        UserPermission::where('user_id', $targetUser->id)->delete();

        foreach ($activePermissionIds as $pId) {
            UserPermission::create([
                'user_id'       => $targetUser->id,
                'permission_id' => $pId,
                'activo'        => true,
            ]);
        }

        AuditLogger::log('USER_PERMISSIONS_UPDATED', 'Permisos', "Permisos personalizados del usuario '{$targetUser->name}' actualizados por {$authUser->name}", $authUser, 'info');

        return response()->json([
            'message' => "Permisos personalizados del usuario '{$targetUser->name}' guardados correctamente.",
            'user_id' => $targetUser->id,
        ]);
    }
}
