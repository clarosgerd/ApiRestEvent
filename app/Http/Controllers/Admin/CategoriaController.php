<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\CategoryController as ApiCategoryController;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Consolidación monolito (21/08/2026), Fase 1c — categorías de un evento
 * existente. Ver PaisController (mismo patrón de delegación) y
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class CategoriaController extends Controller
{
    use DelegatesToApiJson;

    public function store(Request $request, int $evento, ApiCategoryController $api): RedirectResponse
    {
        $validated = $this->mergeAndValidate(StoreCategoryRequest::class, $request, ['event_id' => $evento]);

        return $this->redirectToEventoTab($api->store($validated), $evento, 'categorias', 'Categoría creada correctamente.');
    }

    public function update(UpdateCategoryRequest $request, Category $categoria, ApiCategoryController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->update($request, $categoria), $request->input('evento_id'), 'categorias', 'Categoría actualizada correctamente.');
    }

    public function destroy(Request $request, Category $categoria, ApiCategoryController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->destroy($categoria), $request->input('evento_id'), 'categorias', 'Categoría eliminada correctamente.');
    }
}
