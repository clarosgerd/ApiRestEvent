<?php

namespace App\Http\Middleware\Admin;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consolidación monolito (21/08/2026), Fase 1b — portado 1:1 de
 * admin-eventos, con los nombres de ruta prefijados `admin.*`. Caja se
 * migró en la Fase 1d (mismo día) — el `Route::has()` de acá abajo queda
 * como red de seguridad genérica (si algún día se saca una ruta
 * `admin.caja.*` sin querer, esto avisa en vez de tirar un
 * `RouteNotFoundException` feo) más que por necesidad real ahora mismo.
 * La autorización real ya la hace ApiRestEvent
 * (`AuthorizesEventoScope::assertCanOperarCaja()`), esto es solo la
 * guarda de UX del panel. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class RestrictCajeroToCaja
{
    public function handle(Request $request, Closure $next): Response
    {
        $rol = session('admin_user')['rol'] ?? null;

        if ($rol === 'cajero' && !$request->routeIs('admin.caja.*') && !$request->routeIs('admin.logout')) {
            $eventoId = session('admin_user')['evento_id'] ?? null;

            if ($eventoId && Route::has('admin.caja.index')) {
                return redirect()->route('admin.caja.index', $eventoId);
            }

            abort(403, $eventoId
                ? 'El módulo de Caja todavía no está disponible en este panel — usá admin-eventos por ahora.'
                : 'Tu usuario no tiene un evento asignado.');
        }

        return $next($request);
    }
}
