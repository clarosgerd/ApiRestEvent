<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SocioController as ApiSocioController;
use App\Http\Requests\StoreSocioRequest;
use App\Http\Requests\UpdateSocioRequest;
use App\Models\Socio;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-iv — CRUD de socios de
 * PassToGo (config global, no por evento) — solo super_admin. Mismo
 * patrón de delegación que 1a-1e, portado 1:1 de admin-eventos. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class SocioController extends Controller
{
    use DelegatesToApiJson;

    public function index(ApiSocioController $api): View
    {
        $payload = $api->index()->getData(true);

        return view('admin.socios.index', [
            'socios' => $payload['data'] ?? [],
            'porcentajeTotal' => $payload['porcentaje_total'] ?? 0,
        ]);
    }

    public function store(StoreSocioRequest $request, ApiSocioController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request), 'admin.socios.index');
    }

    public function update(UpdateSocioRequest $request, Socio $socio, ApiSocioController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $socio), 'admin.socios.index');
    }

    public function destroy(Socio $socio, ApiSocioController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($socio), 'admin.socios.index');
    }
}
