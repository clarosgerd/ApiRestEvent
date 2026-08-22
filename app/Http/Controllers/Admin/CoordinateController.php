<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CoordinateController as ApiCoordinateController;
use App\Http\Requests\StoreCoordinateRequest;
use App\Http\Requests\UpdateCoordinateRequest;
use App\Models\Coordinate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Consolidación monolito (21/08/2026), Fase 1c — ver CategoriaController
 * (mismo patrón). Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class CoordinateController extends Controller
{
    use DelegatesToApiJson;

    public function store(Request $request, int $evento, ApiCoordinateController $api): RedirectResponse
    {
        $validated = $this->mergeAndValidate(StoreCoordinateRequest::class, $request, ['event_id' => $evento]);

        return $this->redirectToEventoTab($api->store($validated), $evento, 'mapa', 'Coordenada creada correctamente.');
    }

    public function update(UpdateCoordinateRequest $request, Coordinate $coordinate, ApiCoordinateController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->update($request, $coordinate), $request->input('evento_id'), 'mapa', 'Coordenada actualizada correctamente.');
    }

    public function destroy(Request $request, Coordinate $coordinate, ApiCoordinateController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->destroy($coordinate), $request->input('evento_id'), 'mapa', 'Coordenada eliminada correctamente.');
    }
}
