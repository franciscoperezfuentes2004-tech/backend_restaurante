<?php

namespace App\Traits;

use App\Models\Scopes\SucursalScope;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToSucursal
{
    /**
     * Boot del trait BelongsToSucursal.
     */
    public static function bootBelongsToSucursal(): void
    {
        // Lógica 1: Registra el SucursalScope para filtrar automáticamente
        static::addGlobalScope(new SucursalScope());

        // Lógica 2: En el evento creating, asigna automáticamente la sucursal del usuario actual
        static::creating(function ($model) {
            $hasExplicitSucursal = !empty($model->attributes['sucursal_id'] ?? null);

            if (!$hasExplicitSucursal && auth()->check()) {
                $user = auth()->user();
                $userSucursalId = $user->attributes['sucursal_id'] ?? $user->sucursal_id ?? null;
                if (!empty($userSucursalId)) {
                    $model->sucursal_id = (int) $userSucursalId;
                    $hasExplicitSucursal = true;
                }
            }

            // Si el modelo aún no tiene sucursal_id y no es un usuario super_admin, asigna la sucursal por defecto (1)
            if (!$hasExplicitSucursal) {
                $isSuperAdminUser = ($model instanceof User) && in_array(strtolower((string) ($model->role ?? '')), ['super_admin', 'superadmin', 'súper administrador']);
                if (!$isSuperAdminUser) {
                    $model->sucursal_id = 1;
                }
            }
        });
    }

    /**
     * Lógica 3: Relación con el modelo Sucursal.
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
