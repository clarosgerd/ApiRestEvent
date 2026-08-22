<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PresupuestoCategoriaController as ApiPresupuestoCategoriaController;
use App\Http\Requests\StorePresupuestoCategoriaRequest;
use App\Http\Requests\UpdatePresupuestoCategoriaRequest;
use App\Models\PresupuestoCategoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-iv — catálogo de rubros del
 * presupuesto (config global, no por evento) — solo super_admin. Mismo
 * patrón de delegación que Socio/Organizador. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class PresupuestoCategoriaController extends Controller
{
    use DelegatesToApiJson;

    public function index(ApiPresupuestoCategoriaController $api): View
    {
        $categorias = $this->dataFrom($api->index());

        return view('admin.presupuesto-categorias.index', compact('categorias'));
    }

    public function store(StorePresupuestoCategoriaRequest $request, ApiPresupuestoCategoriaController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request), 'admin.presupuesto-categorias.index');
    }

    public function update(UpdatePresupuestoCategoriaRequest $request, PresupuestoCategoria $categoria, ApiPresupuestoCategoriaController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $categoria), 'admin.presupuesto-categorias.index');
    }

    public function destroy(PresupuestoCategoria $categoria, ApiPresupuestoCategoriaController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($categoria), 'admin.presupuesto-categorias.index');
    }
}
