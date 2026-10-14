<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\TallerCongresoController as ApiTallerCongresoController;
use App\Http\Controllers\TallerPricePeriodController as ApiTallerPricePeriodController;
use App\Http\Requests\StoreTallerPricePeriodRequest;
use App\Http\Requests\UpdateTallerPricePeriodRequest;
use App\Models\Evento;
use App\Models\Taller;
use App\Models\TallerPricePeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Precios por período de un taller (12/10/2026, pedido del usuario) —
 * mismo patrón que Admin\CategoryPricePeriodController. Sin `index()`
 * propio en la API — la lista ya viaja embebida en `TallerResource.periodos`
 * (`TallerCongresoController::show()`), así que se delega ahí.
 */
class TallerPricePeriodController extends Controller
{
    use DelegatesToApiJson;

    public function index(Evento $event, Taller $taller, ApiTallerCongresoController $apiTaller, ApiEventoController $apiEvento): View
    {
        $tallerData = $this->dataFrom($apiTaller->show($event, $taller));

        // Categorías del evento (13/10/2026) — para poblar el <select> de
        // categoría del form de períodos, mismo patrón que
        // Admin\TallerComboController::index() para precios por categoría.
        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        $categorias = $eventoData['categories'] ?? [];

        return view('admin.eventos.talleres.periodos', [
            'eventoId'   => $event->id,
            'tallerId'   => $taller->id,
            'taller'     => $tallerData,
            'periodos'   => $tallerData['periodos'] ?? [],
            'categorias' => $categorias,
        ]);
    }

    public function store(StoreTallerPricePeriodRequest $request, Evento $event, Taller $taller, ApiTallerPricePeriodController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request, $taller), 'admin.talleres.periodos.index', [$event->id, $taller->id]);
    }

    public function update(UpdateTallerPricePeriodRequest $request, Evento $event, Taller $taller, TallerPricePeriod $tallerPricePeriod, ApiTallerPricePeriodController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $tallerPricePeriod), 'admin.talleres.periodos.index', [$event->id, $taller->id]);
    }

    public function destroy(Evento $event, Taller $taller, TallerPricePeriod $tallerPricePeriod, ApiTallerPricePeriodController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($tallerPricePeriod), 'admin.talleres.periodos.index', [$event->id, $taller->id]);
    }
}
