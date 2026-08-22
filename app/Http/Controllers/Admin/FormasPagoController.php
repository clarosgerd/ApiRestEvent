<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FormasPagoController as ApiFormasPagoController;
use App\Http\Requests\StoreFormasPagoRequest;
use App\Http\Requests\UpdateFormasPagoRequest;
use App\Models\FormasPago;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1a — ver PaisController
 * (mismo patrón de delegación) y
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class FormasPagoController extends Controller
{
    use DelegatesToApiJson;

    public function index(ApiFormasPagoController $api): View
    {
        $formasPago = $this->dataFrom($api->index());

        return view('admin.catalogos.formas-pago', compact('formasPago'));
    }

    public function store(StoreFormasPagoRequest $request, ApiFormasPagoController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request), 'admin.catalogos.formas-pago.index');
    }

    public function update(UpdateFormasPagoRequest $request, ApiFormasPagoController $api, FormasPago $formasPago): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $formasPago), 'admin.catalogos.formas-pago.index');
    }

    public function destroy(ApiFormasPagoController $api, FormasPago $formasPago): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($formasPago), 'admin.catalogos.formas-pago.index');
    }
}
