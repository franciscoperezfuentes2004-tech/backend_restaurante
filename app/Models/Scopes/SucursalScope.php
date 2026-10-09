<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SucursalScope implements Scope
{
    /**
     * Aplica el scope para aislar registros según el contexto de sucursal o usuario autenticado.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->check() ? auth()->user() : null;
        $isSuperAdmin = $user && (method_exists($user, 'isSuperAdmin')
            ? $user->isSuperAdmin()
            : in_array(strtolower((string) ($user->role ?? '')), ['super_admin', 'superadmin', 'súper administrador']));

        // 1. Primero, intenta leer el ID inyectado por el middleware (X-Sucursal-ID)
        $sucursalId = app()->bound('current_sucursal_id') ? app('current_sucursal_id') : null;

        // 2. Si no hay header, como fallback por seguridad, usa el del usuario autenticado
        if (!$sucursalId && $user) {
            $sucursalId = $user->sucursal_id;
        }

        // Blindaje de seguridad: si no es Super Admin pero es usuario operativo con sucursal asignada,
        // garantizamos que no pueda forzar otra sucursal modificando encabezados en clientes manipulados
        if ($user && !$isSuperAdmin && !empty($user->sucursal_id)) {
            $sucursalId = $user->sucursal_id;
        }

        // 3. Si el usuario es Súper Admin y NO envió header (quiere ver todo), no aplicar el where
        if ($isSuperAdmin && !app()->bound('current_sucursal_id')) {
            return;
        }

        // 4. Si hay un $sucursalId definitivo, aplica el filtro
        if (!empty($sucursalId)) {
            $builder->where($model->getTable() . '.sucursal_id', $sucursalId);
        }
    }
}
