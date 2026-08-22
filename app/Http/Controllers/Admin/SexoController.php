<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SexoController as ApiSexoController;
use App\Http\Requests\StoreSexoRequest;
use App\Http\Requests\UpdateSexoRequest;
use App\Models\Sexo;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1a — ver PaisController
 * (mismo patrón de delegación) y
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class SexoController extends Controller
{
    use DelegatesToApiJson;

    public function index(ApiSexoController $api): View
    {
        $sexos = $this->dataFrom($api->index());

        return view('admin.catalogos.sexos', compact('sexos'));
    }

    public function store(StoreSexoRequest $request, ApiSexoController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request), 'admin.catalogos.sexos.index');
    }

    public function update(UpdateSexoRequest $request, ApiSexoController $api, Sexo $sexo): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $sexo), 'admin.catalogos.sexos.index');
    }

    public function destroy(ApiSexoController $api, Sexo $sexo): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($sexo), 'admin.catalogos.sexos.index');
    }
}
