<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSucursalContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headerId = $request->header('X-Sucursal-ID');

        if (!empty($headerId) && is_numeric($headerId) && (int) $headerId > 0) {
            app()->instance('current_sucursal_id', (int) $headerId);
        }

        return $next($request);
    }
}
