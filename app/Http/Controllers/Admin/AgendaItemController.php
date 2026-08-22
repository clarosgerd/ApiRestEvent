<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\AgendaItemController as ApiAgendaItemController;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgendaItemRequest;
use App\Http\Requests\UpdateAgendaItemRequest;
use App\Models\AgendaItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Consolidación monolito (21/08/2026), Fase 1c — ver CategoriaController
 * (mismo patrón). `event_id` se fusiona antes de validar (igual que los
 * demás store() de esta fase) porque `StoreAgendaItemRequest` lo exige
 * `required` en el body. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class AgendaItemController extends Controller
{
    use DelegatesToApiJson;

    public function store(Request $request, int $evento, ApiAgendaItemController $api): RedirectResponse
    {
        $validated = $this->mergeAndValidate(StoreAgendaItemRequest::class, $request, ['event_id' => $evento]);

        return $this->redirectToEventoTab($api->store($validated), $evento, 'agenda', 'Ítem de agenda creado correctamente.');
    }

    public function update(UpdateAgendaItemRequest $request, AgendaItem $agendaItem, ApiAgendaItemController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->update($request, $agendaItem), $request->input('evento_id'), 'agenda', 'Ítem de agenda actualizado correctamente.');
    }

    public function destroy(Request $request, AgendaItem $agendaItem, ApiAgendaItemController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->destroy($agendaItem), $request->input('evento_id'), 'agenda', 'Ítem de agenda eliminado correctamente.');
    }
}
