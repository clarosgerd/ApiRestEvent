<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\OrganizadorController as ApiOrganizadorController;
use App\Http\Requests\StoreOrganizadorRequest;
use App\Http\Requests\UpdateOrganizadorRequest;
use App\Models\Organizador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-i — CRUD de organizadores
 * (config global, no por evento) — solo super_admin. Mismo patrón de
 * delegación que 1a-1d, portado 1:1 de admin-eventos. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class OrganizadorController extends Controller
{
    use DelegatesToApiJson;

    public function index(ApiOrganizadorController $api): View
    {
        $organizadores = $this->dataFrom($api->index());

        return view('admin.organizadores.index', compact('organizadores'));
    }

    public function store(StoreOrganizadorRequest $request, ApiOrganizadorController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request), 'admin.organizadores.index');
    }

    public function update(UpdateOrganizadorRequest $request, Organizador $organizador, ApiOrganizadorController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $organizador), 'admin.organizadores.index');
    }

    public function destroy(Organizador $organizador, ApiOrganizadorController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($organizador), 'admin.organizadores.index');
    }

    /**
     * Formas de pago (19/08/2026) — ver
     * elascenso/event/brain/PLAN-INTEGRACION-PAGO-MERU-19082026.md.
     */
    public function formasPago(Organizador $organizador, ApiOrganizadorController $api): View
    {
        $payload = $api->formasPago($organizador)->getData(true);

        return view('admin.organizadores.formas-pago', [
            'organizadorData' => $this->dataFrom($api->show($organizador)),
            'organizadorId' => $organizador->id,
            'formasPago' => $payload['data'] ?? [],
            'usandoDefault' => $payload['usandoDefaultDelSistema'] ?? false,
        ]);
    }

    public function updateFormasPago(Request $request, Organizador $organizador, ApiOrganizadorController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse(
            $api->updateFormasPago($request, $organizador),
            'admin.organizadores.formas-pago',
            [$organizador->id]
        );
    }
}
