<?php

use App\Http\Middleware\NormalizeAuthTokenHeader;
use App\Http\Middleware\RequiresExternalQrLookupSecret;
use App\Http\Middleware\RequiresExternalSyncSecret;
use App\Http\Middleware\RequiresInternalSecret;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [NormalizeAuthTokenHeader::class]);
        // Middleware::alias() REEMPLAZA el array entero en cada llamada (no
        // mergea, ver vendor/laravel/framework/.../Middleware.php) — bug
        // real encontrado acá mismo (07/09/2026): llamar alias() dos veces
        // por separado borraba el alias de la llamada anterior. Los 2 van
        // juntos en una sola llamada.
        // SIP multi-banco (28/08/2026) — 'internal.secret', aplicado solo a
        // la ruta /internal/* (ver routes/api.php), nunca global.
        // Sync de congresos externos (07/09/2026) — 'external.sync.secret',
        // secreto DISTINTO del de arriba, ver RequiresExternalSyncSecret.
        // Lookup de QR para app externa (12/09/2026) —
        // 'external.qrlookup.secret', otro secreto distinto más, ver
        // RequiresExternalQrLookupSecret. TODOS van en esta MISMA llamada
        // (ver comentario arriba) — nunca un alias() nuevo por separado.
        $middleware->alias([
            'internal.secret' => RequiresInternalSecret::class,
            'external.sync.secret' => RequiresExternalSyncSecret::class,
            'external.qrlookup.secret' => RequiresExternalQrLookupSecret::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
