<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\RouteController as ApiRouteController;
use App\Http\Requests\StoreRouteRequest;
use App\Http\Requests\UpdateRouteRequest;
use App\Models\Route as RouteModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Consolidación monolito (21/08/2026), Fase 1c — puntos de la ruta del
 * evento (no confundir con `Illuminate\Support\Facades\Route`, de ahí el
 * alias `RouteModel` para el Eloquent model). Ver CategoriaController
 * (mismo patrón) y
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class RouteController extends Controller
{
    use DelegatesToApiJson;

    public function store(Request $request, int $evento, ApiRouteController $api): RedirectResponse
    {
        $validated = $this->mergeAndValidate(StoreRouteRequest::class, $request, ['event_id' => $evento]);

        return $this->redirectToEventoTab($api->store($validated), $evento, 'mapa', 'Punto de ruta creado correctamente.');
    }

    public function update(UpdateRouteRequest $request, RouteModel $route, ApiRouteController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->update($request, $route), $request->input('evento_id'), 'mapa', 'Punto de ruta actualizado correctamente.');
    }

    public function destroy(Request $request, RouteModel $route, ApiRouteController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->destroy($route), $request->input('evento_id'), 'mapa', 'Punto de ruta eliminado correctamente.');
    }
}
