<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Sync de participantes de congresos externos (07/09/2026) — ver
 * brain/PLAN-SYNC-CONGRESO-EXTERNO-07092026.md. Guarda del endpoint
 * `/internal/event/{event}/participantes-externos/sync`, llamado por un
 * Google Apps Script de un organizador externo (ej. COLABIOCLI 2026), NO
 * por nuestro propio backend.
 *
 * A propósito usa un secreto DISTINTO de `RequiresInternalSecret`
 * (`EXTERNAL_SYNC_SECRET`, no `INTERNAL_API_SECRET`) — ese otro secreto ya
 * protege endpoints sensibles (credenciales SIP) compartidos solo entre
 * nuestros propios backends; si el de un tercero externo se filtra, no debe
 * exponer nada más que este único endpoint de sync. Mismo criterio
 * fail-closed que `RequiresInternalSecret`: sin secreto configurado, se
 * rechaza todo.
 */
class RequiresExternalSyncSecret
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('services.external_sync.secret', '');
        $provided = (string) $request->header('X-External-Sync-Secret', '');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            throw new HttpException(403, 'No autorizado.');
        }

        return $next($request);
    }
}
