<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SIP multi-banco (28/08/2026) — ver
 * brain/api_rest_event/PLAN-SIP-MULTIBANCO-28082026.md.
 *
 * Guarda de los endpoints `/internal/*` que devuelven credenciales SIP
 * reales. A propósito NO usa `auth:admins`/Sanctum (esos son para
 * personas/admins humanos con sesión) — esto es tráfico server-to-server
 * desde el propio backend de `elascenso/event` (nunca un navegador), con
 * un secreto compartido aparte (`INTERNAL_API_SECRET`, mismo valor en el
 * `.env` de los dos proyectos). Si `services.internal.secret` no está
 * configurado, el middleware rechaza TODO (fail-closed, no fail-open) —
 * más seguro que dejarlo pasar por accidente en un entorno mal configurado.
 */
class RequiresInternalSecret
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('services.internal.secret', '');
        $provided = (string) $request->header('X-Internal-Secret', '');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            throw new HttpException(403, 'No autorizado.');
        }

        return $next($request);
    }
}
