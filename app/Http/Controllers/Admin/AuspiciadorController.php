<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\AuspiciadorController as ApiAuspiciadorController;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAuspiciadorRequest;
use App\Http\Requests\UpdateAuspiciadorRequest;
use App\Models\Auspiciador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Consolidación monolito (21/08/2026), Fase 1c — ver CategoriaController
 * (mismo patrón). Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class AuspiciadorController extends Controller
{
    use DelegatesToApiJson;

    public function store(Request $request, int $evento, ApiAuspiciadorController $api): RedirectResponse
    {
        $validated = $this->mergeAndValidate(StoreAuspiciadorRequest::class, $request, ['event_id' => $evento]);

        return $this->redirectToEventoTab($api->store($validated), $evento, 'auspiciadores', 'Auspiciador creado correctamente.');
    }

    public function update(UpdateAuspiciadorRequest $request, Auspiciador $auspiciador, ApiAuspiciadorController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->update($request, $auspiciador), $request->input('evento_id'), 'auspiciadores', 'Auspiciador actualizado correctamente.');
    }

    public function destroy(Request $request, Auspiciador $auspiciador, ApiAuspiciadorController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->destroy($auspiciador), $request->input('evento_id'), 'auspiciadores', 'Auspiciador eliminado correctamente.');
    }
}
