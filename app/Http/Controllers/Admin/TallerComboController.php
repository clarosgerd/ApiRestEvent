<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\TallerCongresoController as ApiTallerCongresoController;
use App\Http\Controllers\TallerComboController as ApiTallerComboController;
use App\Http\Requests\StoreTallerComboRequest;
use App\Http\Requests\UpdateTallerComboRequest;
use App\Models\Evento;
use App\Models\TallerCombo;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Combos de talleres (12/10/2026, pedido del usuario) — "Taller A + Taller
 * B por el precio de 1" o "por $300 fijo". Thin delegate hacia
 * TallerComboController de la API, mismo patrón que Admin\TallerCongresoController.
 */
class TallerComboController extends Controller
{
    use DelegatesToApiJson;

    public function index(Evento $event, ApiEventoController $apiEvento, ApiTallerCongresoController $apiTaller, ApiTallerComboController $apiCombo): View
    {
        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        $talleres = $this->dataFrom($apiTaller->index($event));
        $combos = $this->dataFrom($apiCombo->index($event));

        return view('admin.eventos.talleres.combos', [
            'evento'     => $eventoData,
            'talleres'   => $talleres,
            'combos'     => $combos,
            // Precio por categoría (13/10/2026) — categorías del evento
            // para poblar el <select> del form de precios.
            'categorias' => $eventoData['categories'] ?? [],
        ]);
    }

    public function store(StoreTallerComboRequest $request, Evento $event, ApiTallerComboController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request, $event), 'admin.taller-combos.index', [$event->id]);
    }

    public function update(UpdateTallerComboRequest $request, Evento $event, TallerCombo $tallerCombo, ApiTallerComboController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $tallerCombo), 'admin.taller-combos.index', [$event->id]);
    }

    public function destroy(Evento $event, TallerCombo $tallerCombo, ApiTallerComboController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($tallerCombo), 'admin.taller-combos.index', [$event->id]);
    }
}
