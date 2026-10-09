<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SucursalScope implements Scope
{
    /**
     * Aplica el scope para aislar registros según la sucursal del usuario autenticado.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $user = auth()->user();

            // Si es un "Súper Admin" (que puede ver todo), no filtramos por sucursal
            $isSuperAdmin = method_exists($user, 'isSuperAdmin')
                ? $user->isSuperAdmin()
                : in_array(strtolower((string) ($user->role ?? '')), ['super_admin', 'superadmin', 'súper administrador']);

            if (!$isSuperAdmin) {
                // Calificamos con el nombre de la tabla para evitar colisiones en sentencias con JOINs
                $builder->where($model->getTable() . '.sucursal_id', $user->sucursal_id);
            }
        }
    }
}
