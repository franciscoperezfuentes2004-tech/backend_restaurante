<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // El prefijo 'api' genera la ruta /api/broadcasting/auth exacta que React está pidiendo
        Broadcast::routes([
            'prefix' => 'api',
            'middleware' => ['api', 'auth:sanctum']
        ]);

        // Mantenemos también /broadcasting/auth para compatibilidad global
        Broadcast::routes([
            'middleware' => ['api', 'auth:sanctum']
        ]);

        require base_path('routes/channels.php');
    }
}
