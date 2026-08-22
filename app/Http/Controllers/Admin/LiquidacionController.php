<?php

namespace App\Http\Controllers\Admin;

use App\Actions\LiquidarEventoAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\LiquidacionController as ApiLiquidacionController;
use App\Models\Evento;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-iv — liquidación de
 * utilidades por evento. Todo el cálculo vive en LiquidarEventoAction del
 * lado API (delegado acá, no reimplementado) — solo super_admin
 * (assertIsSuperAdmin() ya lo exige dentro de cada método de la API). Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class LiquidacionController extends Controller
{
    public function show(Evento $event, ApiEventoController $apiEvento, ApiLiquidacionController $apiLiquidacion, LiquidarEventoAction $action): View
    {
        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        $showPayload = $apiLiquidacion->show($event)->getData(true);
        $liquidacion = ($showPayload['success'] ?? false) ? ($showPayload['data'] ?? null) : null;

        $preview = null;
        if (!$liquidacion) {
            $previewPayload = $apiLiquidacion->preview($event, $action)->getData(true);
            $preview = ($previewPayload['success'] ?? false) ? $previewPayload : null;
        }

        return view('admin.eventos.liquidacion', [
            'evento' => $eventoData,
            'liquidacion' => $liquidacion,
            'preview' => $preview,
        ]);
    }

    public function store(Evento $event, ApiLiquidacionController $api, LiquidarEventoAction $action): RedirectResponse
    {
        $payload = $api->store($event, $action)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors(['general' => $payload['error'] ?? 'No se pudo liquidar el evento.']);
        }

        return redirect()->route('admin.liquidacion.show', $event->id)->with('status', 'Evento liquidado correctamente.');
    }
}
