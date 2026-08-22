<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SubtipoEventoController as ApiSubtipoEventoController;
use App\Http\Controllers\TipoEventoController as ApiTipoEventoController;
use App\Http\Requests\StoreSubtipoEventoRequest;
use App\Http\Requests\UpdateSubtipoEventoRequest;
use App\Models\SubtipoEvento;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1a — ver PaisController
 * (mismo patrón de delegación) y
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class SubtipoEventoController extends Controller
{
    use DelegatesToApiJson;

    public function index(ApiSubtipoEventoController $api, ApiTipoEventoController $apiTipos): View
    {
        $subtipos = $this->dataFrom($api->index());
        $tipos = $this->dataFrom($apiTipos->adminIndex());

        return view('admin.catalogos.subtipos-evento', compact('subtipos', 'tipos'));
    }

    public function store(StoreSubtipoEventoRequest $request, ApiSubtipoEventoController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request), 'admin.catalogos.subtipos-evento.index');
    }

    public function update(UpdateSubtipoEventoRequest $request, ApiSubtipoEventoController $api, SubtipoEvento $subtipoEvento): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $subtipoEvento), 'admin.catalogos.subtipos-evento.index');
    }

    public function destroy(ApiSubtipoEventoController $api, SubtipoEvento $subtipoEvento): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($subtipoEvento), 'admin.catalogos.subtipos-evento.index');
    }
}
