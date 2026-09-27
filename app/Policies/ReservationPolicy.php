<?php

namespace App\Policies;

use App\Models\User;
use App\Services\PermissionService;

class ReservationPolicy
{
    public function manage(User $user): bool
    {
        return PermissionService::hasPermission($user, 'reservations.manage');
    }
}
