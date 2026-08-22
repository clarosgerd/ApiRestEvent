<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SincronizarChronoTrackAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\ResultadoController as ApiResultadoController;
use App\Models\Evento;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-ii — botón "Sincronizar con
 * ChronoTrack". El campo `chronotrackEventId` se sigue editando vía
 * Admin\EventoController::update() (reusado, no hay endpoint propio para
 * eso) — este controller solo agrega la pantalla y el botón. Delegando en
 * ResultadoController::sincronizarChronoTrack() de la API (no en la
 * Action directo — ese método también valida chronotrack_event_id y hace
 * el scoping de autorización, no hay que duplicarlo acá) — llamada
 * in-process, así que el timeout largo que necesitaba el proxy HTTP
 * (120s) ya no hace falta configurar: no hay red de por medio. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class ChronoTrackController extends Controller
{
    public function index(Evento $event, ApiEventoController $apiEvento): View
    {
        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        return view('admin.eventos.resultados', ['evento' => $eventoData]);
    }

    public function sincronizar(Evento $event, ApiResultadoController $api, SincronizarChronoTrackAction $action): RedirectResponse
    {
        $payload = $api->sincronizarChronoTrack($event, $action)->getData(true);

        if (!($payload['success'] ?? false)) {
            return redirect()->route('admin.chronotrack.index', $event->id)->withErrors(['general' => $payload['error'] ?? 'No se pudo sincronizar.']);
        }

        return redirect()->route('admin.chronotrack.index', $event->id)->with('syncResult', [
            'procesados'    => $payload['procesados'] ?? 0,
            'dns'           => $payload['dns'] ?? 0,
            'dnf'           => $payload['dnf'] ?? 0,
            'no_vinculados' => $payload['no_vinculados'] ?? [],
        ]);
    }
}
