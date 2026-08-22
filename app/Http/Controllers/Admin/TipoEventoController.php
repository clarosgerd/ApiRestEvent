<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TipoEventoController as ApiTipoEventoController;
use App\Http\Requests\StoreTipoEventoRequest;
use App\Http\Requests\UpdateTipoEventoRequest;
use App\Models\TipoEvento;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1a — ver PaisController
 * (mismo patrón de delegación). Usa `adminIndex()`, no `index()` — ese
 * sigue siendo el endpoint público sin auth que consume el alta de evento,
 * no se toca. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class TipoEventoController extends Controller
{
    use DelegatesToApiJson;

    public function index(ApiTipoEventoController $api): View
    {
        $tipos = $this->dataFrom($api->adminIndex());

        return view('admin.catalogos.tipos-evento', compact('tipos'));
    }

    public function store(StoreTipoEventoRequest $request, ApiTipoEventoController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request), 'admin.catalogos.tipos-evento.index');
    }

    public function update(UpdateTipoEventoRequest $request, ApiTipoEventoController $api, TipoEvento $tipoEvento): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $tipoEvento), 'admin.catalogos.tipos-evento.index');
    }

    public function destroy(ApiTipoEventoController $api, TipoEvento $tipoEvento): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($tipoEvento), 'admin.catalogos.tipos-evento.index');
    }
}
