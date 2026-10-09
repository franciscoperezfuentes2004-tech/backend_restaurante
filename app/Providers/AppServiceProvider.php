<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') !== 'local') {
            URL::forceScheme('https');
        }

        Validator::extend('strip_tags', function ($attribute, $value, $parameters, $validator) {
            return true;
        });

        // Macro de Caché Multi-Tenant: Aislamiento de memoria por sucursal
        Cache::macro('tenant', function () {
            // Intentamos obtener la sucursal del middleware o del usuario autenticado
            $sucursalId = app()->bound('current_sucursal_id') 
                          ? app('current_sucursal_id') 
                          : (auth()->check() ? auth()->user()->sucursal_id : 'global');

            if (empty($sucursalId)) {
                $sucursalId = 'global';
            }

            // Retornamos la instancia de caché etiquetada para esta sucursal específica
            if (Cache::supportsTags()) {
                return Cache::tags(['sucursal_' . $sucursalId]);
            }

            return Cache::store();
        });

        // 1. Límite estricto para Login (Evita ataques de fuerza bruta)
        // Permite solo 5 intentos por minuto por correo electrónico o por IP.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email') ?: $request->ip());
        });

        // 2. Límite para Reservaciones (Evita spam de reservas falsas)
        // Permite un máximo de 3 intentos de reserva por minuto por IP.
        RateLimiter::for('reservaciones', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });
        RateLimiter::for('reservations', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // 3. Límite para Reseñas (Evita bombardeo de calificaciones)
        // Permite un máximo de 2 reseñas por minuto por IP.
        RateLimiter::for('resenas', function (Request $request) {
            return Limit::perMinute(2)->by($request->ip());
        });
        RateLimiter::for('reviews', function (Request $request) {
            return Limit::perMinute(2)->by($request->ip());
        });

        // 4. Observers para purgado selectivo de caché por sucursal
        \App\Models\Dish::observe(\App\Observers\DishObserver::class);
        \App\Models\Category::observe(\App\Observers\CategoryObserver::class);
    }
}
