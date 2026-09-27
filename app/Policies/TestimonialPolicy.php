<?php

namespace App\Policies;

use App\Models\User;
use App\Services\PermissionService;

class TestimonialPolicy
{
    public function reply(User $user): bool
    {
        return PermissionService::hasPermission($user, 'reviews.reply');
    }

    public function hide(User $user): bool
    {
        return PermissionService::hasPermission($user, 'reviews.hide');
    }

    public function restore(User $user): bool
    {
        return PermissionService::hasPermission($user, 'reviews.restore');
    }

    public function moderateImage(User $user): bool
    {
        return PermissionService::hasPermission($user, 'reviews.moderate_images');
    }

    public function viewAudit(User $user): bool
    {
        return PermissionService::hasPermission($user, 'reviews.view_audit');
    }
}
