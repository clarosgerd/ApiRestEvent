<?php

namespace App\Http\Controllers\Inscripcion;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController;
use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consolidación monolito (22/08/2026), Fase 2 — sirve el shell de la SPA de
 * `elascenso-blade` (ex `App\Http\Controllers\HomeController` de ese repo).
 * Copy 1:1 de la vista (`resources/views/inscripcion/home.blade.php` +
 * `partials/*`) — Fase 2 de la consolidación NO toca una sola línea de JS,
 * mismo criterio que la Fase 1 de `elascenso-blade` (su propia migración
 * interna, distinta de esta) dejó pendiente para su Fase 2 propia (ver
 * [[project_migracion_blade_status]]).
 *
 * Único cambio real respecto al original: la resolución de `$shareEvento`
 * pasa de `ApiRestEventClient::forward('GET', '/event/{id}')` (HTTP real)
 * a invocar `EventoController::show()` in-process — misma regla dura que
 * toda la Fase 1 (nunca reimplementar, siempre llamar el controller de la
 * API que ya existe).
 */
class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $anioActual = (int) date('Y');
        $anioMinimo = 1920;
        $apiBase = 'api';
        $externalApiRetries = (int) config('services.apirestevent.retries', 1);
        $externalApiRetryMs = (int) config('services.apirestevent.retry_delay', 400);

        $eventoIdParam = trim((string) $request->query('evento', ''));
        $shareEvento = null;

        if ($eventoIdParam !== '') {
            $evento = Evento::find($eventoIdParam);
            if ($evento) {
                $decoded = app(EventoController::class)->show($evento)->getData(true);
                if (! empty($decoded['success']) && isset($decoded['eventos'])) {
                    $shareEvento = $decoded['eventos'];
                }
            }
        }

        $basePageUrl = $request->url();

        $pageTitle = 'Pass2Go - registro Evento';
        $pageDescription = 'Registro de eventos deportivos y benéficos.';
        $pageImage = '';
        $pageUrl = $basePageUrl.($eventoIdParam !== '' ? '?evento='.rawurlencode($eventoIdParam) : '');

        if ($shareEvento) {
            $pageTitle = ($shareEvento['name'] ?? $pageTitle).' · Pass2Go';
            $pageDescription = $shareEvento['description'] ?? $pageDescription;
            $pageImage = $shareEvento['image'] ?? '';
        }

        return view('inscripcion.home', compact(
            'anioActual',
            'anioMinimo',
            'apiBase',
            'externalApiRetries',
            'externalApiRetryMs',
            'eventoIdParam',
            'pageTitle',
            'pageDescription',
            'pageImage',
            'pageUrl',
        ));
    }
}
