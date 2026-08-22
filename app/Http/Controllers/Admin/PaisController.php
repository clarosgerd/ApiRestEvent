<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaisController as ApiPaisController;
use App\Http\Requests\StorePaisRequest;
use App\Http\Requests\UpdatePaisRequest;
use App\Models\Pais;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1a — delega 100% en
 * App\Http\Controllers\PaisController (la misma que sirve
 * /api/v1/catalogos/paises), sin reimplementar CRUD/autorización/
 * auditoría. Ver DelegatesToApiJson y
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class PaisController extends Controller
{
    use DelegatesToApiJson;

    public function index(ApiPaisController $api): View
    {
        $paises = $this->dataFrom($api->index());

        return view('admin.catalogos.paises', compact('paises'));
    }

    public function store(StorePaisRequest $request, ApiPaisController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request), 'admin.catalogos.paises.index');
    }

    public function update(UpdatePaisRequest $request, ApiPaisController $api, Pais $pais): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $pais), 'admin.catalogos.paises.index');
    }

    public function destroy(ApiPaisController $api, Pais $pais): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($pais), 'admin.catalogos.paises.index');
    }
}
