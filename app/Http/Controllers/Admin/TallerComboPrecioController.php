<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TallerComboPrecioController as ApiTallerComboPrecioController;
use App\Http\Requests\StoreTallerComboPrecioRequest;
use App\Http\Requests\UpdateTallerComboPrecioRequest;
use App\Models\Evento;
use App\Models\TallerCombo;
use App\Models\TallerComboPrecio;
use Illuminate\Http\RedirectResponse;

/**
 * Precio por categoría de un combo de talleres (13/10/2026, pedido del
 * usuario) — el mismo combo puede costar distinto según la categoría del
 * participante. Thin delegate hacia TallerComboPrecioController de la API,
 * mismo patrón que Admin\TallerComboController.
 */
class TallerComboPrecioController extends Controller
{
    use DelegatesToApiJson;

    public function store(StoreTallerComboPrecioRequest $request, Evento $event, TallerCombo $tallerCombo, ApiTallerComboPrecioController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request, $tallerCombo), 'admin.taller-combos.index', [$event->id]);
    }

    public function update(UpdateTallerComboPrecioRequest $request, Evento $event, TallerCombo $tallerCombo, TallerComboPrecio $tallerComboPrecio, ApiTallerComboPrecioController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $tallerComboPrecio), 'admin.taller-combos.index', [$event->id]);
    }

    public function destroy(Evento $event, TallerCombo $tallerCombo, TallerComboPrecio $tallerComboPrecio, ApiTallerComboPrecioController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($tallerComboPrecio), 'admin.taller-combos.index', [$event->id]);
    }
}
