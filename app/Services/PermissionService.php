<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserPermission;

class PermissionService
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
        'menu.manage'         => 'gestionar_platillos',
        'menu.view'           => 'ver_platillos',
        'orders.view'         => 'ver_pedidos',
        'orders.manage'       => 'ver_pedidos',
        'reservations.manage' => 'gestionar_reservaciones',
        'reservations.view'   => 'ver_reservaciones',
        'delivery.view'       => 'ver_delivery',
        'delivery.manage'     => 'ver_delivery',
        'promotions.manage'   => 'gestionar_promociones',
        'reviews.reply'       => 'responder_resenas',
        'reviews.view'        => 'ver_resenas',
        'inventory.view'      => 'ver_inventario',
        'inventory.manage'    => 'gestionar_inventario',
        'reports.view'        => 'ver_reportes',
        'reports.manage'      => 'ver_reportes',
        'audit.view'          => 'ver_bitacora',
        'audit.manage'        => 'ver_bitacora',
        'users.manage'        => 'ver_bitacora',
        'users.view'          => 'ver_bitacora',
        'config.view'         => 'configuracion',
        'config.manage'       => 'configuracion',
    ];

    private static function normalizeClave(string $clave): string
    {
        $c = strtolower(trim($clave));
        return self::CLAVE_MAP[$c] ?? $c;
    }

    public static function getPermissions(?string $role): array
    {
        if (!$role) {
            return [];
        }

        $allDbClaves = Permission::pluck('clave')->toArray();
        $allDotKeys = array_keys(self::CLAVE_MAP);

        if (in_array(strtolower($role), ['super_admin', 'admin'], true)) {
            return array_values(array_unique(array_merge($allDbClaves, $allDotKeys)));
        }

        $activePermIds = RolePermission::where(function ($q) use ($role) {
            $q->where('role', $role)->orWhere('role', strtolower($role));
        })->where('activo', true)->pluck('permission_id')->toArray();

        $activeClaves = Permission::whereIn('id', $activePermIds)->pluck('clave')->toArray();
        $result = $activeClaves;

        foreach (self::CLAVE_MAP as $alias => $targetClave) {
            if (in_array($targetClave, $activeClaves, true)) {
                $result[] = $alias;
            }
        }

        return array_values(array_unique($result));
    }

    public static function hasPermission(?User $user, string $permission): bool
    {
        if (!$user || !$user->role) {
            return false;
        }

        if (in_array(strtolower($user->role), ['super_admin', 'admin'], true)) {
            return true;
        }

        $normClave = self::normalizeClave($permission);

        $perm = Permission::where('clave', $normClave)
            ->orWhere('clave', $permission)
            ->first();

        if (!$perm) {
            return true;
        }

        $userOverride = UserPermission::where('user_id', $user->id)
            ->where('permission_id', $perm->id)
            ->first();

        if ($userOverride !== null) {
            return (bool) $userOverride->activo;
        }

        $rolePerm = RolePermission::where(function ($q) use ($user) {
            $q->where('role', $user->role)
              ->orWhere('role', strtolower($user->role));
        })->where('permission_id', $perm->id)->first();

        return $rolePerm ? (bool) $rolePerm->activo : false;
    }
}
