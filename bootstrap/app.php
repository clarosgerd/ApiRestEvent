<?php

use App\Http\Middleware\Admin\EnsureSuperAdminSession;
use App\Http\Middleware\Admin\InjectAdminSessionToken;
use App\Http\Middleware\Admin\RestrictCajeroToCaja;
use App\Http\Middleware\NormalizeAuthTokenHeader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Consolidación monolito (21/08/2026) — panel admin, ex
        // admin-eventos. Ver
        // brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(__DIR__.'/../routes/admin.php');
            // Fase 2 (22/08/2026) — shell público, ex elascenso-blade. Ver
            // brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(__DIR__.'/../routes/inscripcion.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [NormalizeAuthTokenHeader::class]);
        // Fase 2 — las rutas de routes/inscripcion.php son stateless (token
        // de Persona/Club reenviado a mano por header, sin sesión de
        // Laravel autenticando al usuario final), mismo criterio que ya
        // documentaba elascenso-blade/bootstrap/app.php: CSRF protege
        // contra un submit malicioso con cookies de sesión ya puestas por
        // el navegador, escenario que no existe acá. No afecta al panel
        // admin (Blade con @csrf normal, sigue protegido — esta lista es
        // aditiva, Laravel arranca sin ningún path exento por defecto).
        $middleware->validateCsrfTokens(except: [
            'persona/*',
            'club/login',
            'club/logout',
            'registro',
            'registro/*',
            'webhooks/*',
            'api/*',
        ]);
        $middleware->alias([
            'admin.token'           => InjectAdminSessionToken::class,
            'admin.superadmin'      => EnsureSuperAdminSession::class,
            'admin.restrict-cajero' => RestrictCajeroToCaja::class,
        ]);
        // Laravel reordena el middleware por una lista de prioridad interna
        // (Kernel::$middlewarePriority) — Authenticate SIEMPRE corre antes
        // que cualquier middleware "no listado" ahí, sin importar el orden
        // declarado en la ruta. InjectAdminSessionToken tiene que inyectar
        // el header Authorization ANTES de que auth:admins lo lea, así que
        // se registra explícitamente con prioridad más alta. Encontrado en
        // vivo durante la Fase 1a — ver
        // brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: InjectAdminSessionToken::class,
        );
        // Mismo criterio que admin-eventos: un guest sin sesión que pega a
        // /admin/* vuelve al login del panel en vez del 401 JSON genérico
        // que sí corresponde para /api/v1/* (personas/clubes/admins vía
        // Sanctum, consumido por elascenso/event — no se toca).
        $middleware->redirectGuestsTo(
            fn ($request) => $request->is('admin/*') ? route('admin.login') : null
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
