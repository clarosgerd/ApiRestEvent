<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\CiudadController as ApiCiudadController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaisController as ApiPaisController;
use App\Http\Requests\StoreCiudadRequest;
use App\Http\Requests\UpdateCiudadRequest;
use App\Models\Ciudad;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1a — ver PaisController
 * (mismo patrón de delegación) y
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class CiudadController extends Controller
{
    use DelegatesToApiJson;

    public function index(ApiCiudadController $api, ApiPaisController $apiPaises): View
    {
        $ciudades = $this->dataFrom($api->index());
        $paises = $this->dataFrom($apiPaises->index());

        return view('admin.catalogos.ciudades', compact('ciudades', 'paises'));
    }

    public function store(StoreCiudadRequest $request, ApiCiudadController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request), 'admin.catalogos.ciudades.index');
    }

    public function update(UpdateCiudadRequest $request, ApiCiudadController $api, Ciudad $ciudad): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $ciudad), 'admin.catalogos.ciudades.index');
    }

    public function destroy(ApiCiudadController $api, Ciudad $ciudad): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($ciudad), 'admin.catalogos.ciudades.index');
    }
}
