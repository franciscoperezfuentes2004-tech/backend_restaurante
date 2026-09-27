<?php

namespace App\Policies;

use App\Models\User;
use App\Services\PermissionService;

class UserPolicy
{
    public function manage(User $user): bool
    {
        return PermissionService::hasPermission($user, 'users.manage');
    }
}
