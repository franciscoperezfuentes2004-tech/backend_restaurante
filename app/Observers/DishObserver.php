<?php

namespace App\Observers;

use App\Models\Dish;
use Illuminate\Support\Facades\Cache;

class DishObserver
{
    /**
     * Handle the Dish "saved" event (created or updated).
     */
    public function saved(Dish $dish): void
    {
        $this->flushTenantCache($dish);
    }

    /**
     * Handle the Dish "deleted" event.
     */
    public function deleted(Dish $dish): void
    {
        $this->flushTenantCache($dish);
    }

    /**
     * Purgar selectivamente la memoria caché de la sucursal correspondiente.
     */
    private function flushTenantCache(Dish $dish): void
    {
        $sucursalId = $dish->sucursal_id ?? (app()->bound('current_sucursal_id') ? app('current_sucursal_id') : null);

        if ($sucursalId && Cache::supportsTags()) {
            Cache::tags(['sucursal_' . $sucursalId])->flush();
        } else {
            Cache::tenant()->flush();
        }
    }
}
