<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

// Algunos hostings cPanel/CloudLinux corren PHP vía mod_lsapi (FastCGI), que no
// reenvía el header Authorization al proceso PHP (a diferencia de mod_php) —
// ni siquiera con CGIPassAuth On, que solo cubre mod_cgi/mod_fcgid/mod_proxy_fcgi.
// Verificado en producción (events.inscrito.net / api.inscrito.net): un token
// recién emitido por /persona/register queda 100% inválido en /persona/me
// porque Sanctum nunca ve el Bearer token.
// Mientras se resuelve del lado de hosting, los clientes (elascenso/event)
// mandan el mismo token también en X-Auth-Token — este middleware lo usa como
// respaldo solo cuando Authorization no llegó, sin pisar un Authorization real.
class NormalizeAuthTokenHeader
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->headers->has('Authorization') && $request->headers->has('X-Auth-Token')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->header('X-Auth-Token'));
        }

        return $next($request);
    }
}
