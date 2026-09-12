<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Lookup de participante por QR para la app externa Android/iOS
 * (12/09/2026) — un equipo aparte (fuera de nuestro alcance) va a construir
 * esa app; nosotros solo exponemos un endpoint de lectura para que, al
 * escanear el QR de referencia (`ReferenceQrService`, ya existía para
 * Acreditación en admin-eventos), puedan mostrar quién es la persona.
 *
 * A propósito usa un secreto DISTINTO de `RequiresInternalSecret` (SIP) y
 * de `RequiresExternalSyncSecret` (COLABIOCLI) — mismo criterio ya
 * establecido: si el secreto de ESTE integrador externo se filtra, no debe
 * exponer ningún otro endpoint. Fail-closed: sin secreto configurado, se
 * rechaza todo.
 */
class RequiresExternalQrLookupSecret
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('services.external_qr_lookup.secret', '');
        $provided = (string) $request->header('X-External-Qr-Lookup-Secret', '');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            throw new HttpException(403, 'No autorizado.');
        }

        return $next($request);
    }
}
