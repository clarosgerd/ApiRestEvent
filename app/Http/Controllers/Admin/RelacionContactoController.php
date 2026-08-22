<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\RelacionContactoController as ApiRelacionContactoController;
use App\Http\Requests\StoreRelacionContactoRequest;
use App\Http\Requests\UpdateRelacionContactoRequest;
use App\Models\RelacionContacto;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1a — ver PaisController
 * (mismo patrón de delegación) y
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class RelacionContactoController extends Controller
{
    use DelegatesToApiJson;

    public function index(ApiRelacionContactoController $api): View
    {
        $relaciones = $this->dataFrom($api->index());

        return view('admin.catalogos.relaciones-contacto', compact('relaciones'));
    }

    public function store(StoreRelacionContactoRequest $request, ApiRelacionContactoController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request), 'admin.catalogos.relaciones-contacto.index');
    }

    public function update(UpdateRelacionContactoRequest $request, ApiRelacionContactoController $api, RelacionContacto $relacionContacto): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $relacionContacto), 'admin.catalogos.relaciones-contacto.index');
    }

    public function destroy(ApiRelacionContactoController $api, RelacionContacto $relacionContacto): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($relacionContacto), 'admin.catalogos.relaciones-contacto.index');
    }
}
