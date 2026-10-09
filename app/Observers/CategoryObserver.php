<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryObserver
{
    /**
     * Handle the Category "saved" event (created or updated).
     */
    public function saved(Category $category): void
    {
        $this->flushTenantCache($category);
    }

    /**
     * Handle the Category "deleted" event.
     */
    public function deleted(Category $category): void
    {
        $this->flushTenantCache($category);
    }

    /**
     * Purgar selectivamente la memoria caché de la sucursal correspondiente.
     */
    private function flushTenantCache(Category $category): void
    {
        $sucursalId = $category->sucursal_id ?? (app()->bound('current_sucursal_id') ? app('current_sucursal_id') : null);

        if ($sucursalId && Cache::supportsTags()) {
            Cache::tags(['sucursal_' . $sucursalId])->flush();
        } else {
            Cache::tenant()->flush();
        }
    }
}
