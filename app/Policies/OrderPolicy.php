<?php

namespace App\Policies;

use App\Models\User;
use App\Services\PermissionService;

class OrderPolicy
{
    public function view(User $user): bool
    {
        return PermissionService::hasPermission($user, 'orders.view');
    }

    public function manage(User $user): bool
    {
        return PermissionService::hasPermission($user, 'orders.manage');
    }

    /**
     * Determina si el usuario tiene autorización para transicionar estados operativos de pedidos.
     * Roles autorizados estrictamente: kitchen, admin, manager (y alias cocina, super_admin, gerente).
     * Meseros, repartidores y clientes no pueden ejecutar estas transiciones de cocina.
     */
    public function updateStatus(User $user, ?\App\Models\Order $order = null): bool
    {
        return $user->hasAnyRole(['kitchen', 'cocina', 'admin', 'super_admin', 'manager', 'gerente']);
    }

    /**
     * Determina si el usuario tiene autorización estricta para asignarse un pedido de reparto en calle.
     * Restricción Quirúrgica: Sólo usuarios con el rol exacto de 'repartidor' (o 'driver').
     * Ni cajeros, ni meseros, ni administradores pueden autoasignarse pedidos en calle.
     */
    public function assignDelivery(User $user, ?\App\Models\Order $order = null): bool
    {
        $role = strtolower($user->role ?? '');
        return in_array($role, ['repartidor', 'driver'], true);
    }
}

