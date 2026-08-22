<?php

namespace App\Http\Middleware\Admin;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarda de UX del lado del panel (portada 1:1 de admin-eventos) — la
 * autorización real la sigue haciendo
 * AuthorizesEventoScope::assertIsSuperAdmin() dentro del controller de la
 * API que cada Admin\* controller delega. Si esto se salteara, el request
 * igual sería rechazado con 403 del lado de la API — ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class EnsureSuperAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((session('admin_user')['rol'] ?? null) !== 'super_admin') {
            abort(403, 'Esta sección requiere rol super_admin.');
        }

        return $next($request);
    }
}
