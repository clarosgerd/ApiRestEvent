<?php

use App\Http\Middleware\NormalizeAuthTokenHeader;
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
        // SIP multi-banco (28/08/2026) — alias nuevo, aplicado solo a la
        // ruta /internal/* (ver routes/api.php), nunca global.
        $middleware->alias(['internal.secret' => RequiresInternalSecret::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
