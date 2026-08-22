<?php

namespace App\Http\Middleware\Admin;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consolidación monolito (21/08/2026), Fase 1a — ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 *
 * Antes (admin-eventos, app separada): el login guardaba el token de
 * Sanctum en `session('admin_token')` y cada llamada HTTP a ApiRestEvent
 * mandaba ese token como header `Authorization: Bearer …`
 * (`ApiRestEventClient::buildHeaders()`).
 *
 * Ahora (mismo proceso): no hay HTTP real entre el panel y la API, pero el
 * guard `admins` (Sanctum) sigue resolviendo el usuario autenticado leyendo
 * ese mismo header — así que en vez de reimplementar la resolución de
 * token, esta middleware simplemente copia `session('admin_token')` al
 * header `Authorization` del propio request ANTES de que corra
 * `auth:admins`. Con esto, `AuthorizesEventoScope::assertIsSuperAdmin()` (y
 * el resto de los controllers de la API) funcionan EXACTAMENTE igual sin
 * ningún cambio — no hay 2 mecanismos de auth, hay uno solo, alimentado
 * desde 2 lugares distintos (header real para /api/v1/*, sesión para
 * /admin/*).
 */
class InjectAdminSessionToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Leer la sesión DEL REQUEST explícitamente (`$request->session()`),
        // no el helper global `session()` — evita depender de que el
        // binding del contenedor ('session'/'session.store') ya esté
        // sincronizado con la sesión de este request puntual en todos los
        // casos (StartSession, más arriba en el stack 'web', normalmente
        // los deja iguales, pero no hay que asumirlo).
        $token = $request->hasSession() ? $request->session()->get('admin_token') : null;

        if ($token && !$request->headers->has('Authorization')) {
            $request->headers->set('Authorization', 'Bearer '.$token);
        }

        return $next($request);
    }
}
