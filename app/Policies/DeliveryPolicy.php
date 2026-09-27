<?php

namespace App\Policies;

use App\Models\User;
use App\Services\PermissionService;

class DeliveryPolicy
{
    public function view(User $user): bool
    {
        return PermissionService::hasPermission($user, 'delivery.view');
    }

    public function manage(User $user): bool
    {
        return PermissionService::hasPermission($user, 'delivery.manage');
    }
}
