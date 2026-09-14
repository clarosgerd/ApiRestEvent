<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\GeneroController as ApiGeneroController;
use App\Http\Requests\StoreGeneroRequest;
use App\Http\Requests\UpdateGeneroRequest;
use App\Models\Genero;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Catálogo de género de participante (31/08/2026, sincronizado al monolito
 * 14/09/2026) — mismo patrón de delegación que Admin\TipoEventoController.
 * Usa `adminIndex()`, no `index()` — ese sigue siendo el endpoint público
 * sin auth que consume elascenso/event, no se toca.
 */
class GeneroController extends Controller
{
    use DelegatesToApiJson;

    public function index(ApiGeneroController $api): View
    {
        $generos = $this->dataFrom($api->adminIndex());

        return view('admin.catalogos.generos', compact('generos'));
    }

    public function store(StoreGeneroRequest $request, ApiGeneroController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request), 'admin.catalogos.generos.index');
    }

    public function update(UpdateGeneroRequest $request, ApiGeneroController $api, Genero $genero): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $genero), 'admin.catalogos.generos.index');
    }

    public function destroy(ApiGeneroController $api, Genero $genero): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($genero), 'admin.catalogos.generos.index');
    }
}
