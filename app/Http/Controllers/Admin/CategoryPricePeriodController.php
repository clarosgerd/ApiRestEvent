<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\CategoryController as ApiCategoryController;
use App\Http\Controllers\CategoryPricePeriodController as ApiCategoryPricePeriodController;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryPricePeriodRequest;
use App\Http\Requests\UpdateCategoryPricePeriodRequest;
use App\Models\Category;
use App\Models\CategoryPricePeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1c — precios por período de
 * una categoría. Pantalla propia (no vive dentro de una pestaña de
 * eventos.edit), por eso redirige a `admin.categorias.periodos.index`, no
 * a `redirectToEventoTab`. La API no tiene `index()` propio para esto — la
 * lista de períodos ya viaja embebida en `CategoryResource.periodos`
 * (`CategoryController::show()`), así que se delega ahí. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class CategoryPricePeriodController extends Controller
{
    use DelegatesToApiJson;

    public function index(Request $request, Category $category, ApiCategoryController $apiCategory): View
    {
        // Sync 14/09/2026 (Lote 1, cherry-pick 24fb412) — CategoryController::
        // show() dejó de devolver un JsonResource "pelado" y pasó a devolver
        // un JsonResponse ya armado ({'success', 'category' => ...}), fix de
        // un bug real donde el wrapper default de JsonResource ({"data":...})
        // no calzaba con lo que este mismo controller esperaba en 'category'.
        // Ya no hace falta ->response() (eso solo existe en objetos
        // Responsable, no en un JsonResponse ya construido).
        $categoria = $apiCategory->show($category)->getData(true)['category'];

        return view('admin.categorias.periodos', [
            'categoryId' => $category->id,
            'eventoId'   => $request->query('evento_id'),
            'categoria'  => $categoria,
            'periodos'   => $categoria['periodos'] ?? [],
        ]);
    }

    public function store(Request $request, Category $category, ApiCategoryPricePeriodController $api): RedirectResponse
    {
        $validated = $this->mergeAndValidate(StoreCategoryPricePeriodRequest::class, $request, []);

        return $this->redirectToPeriodos($api->store($validated, $category), $category->id, $request);
    }

    public function update(UpdateCategoryPricePeriodRequest $request, CategoryPricePeriod $categoryPricePeriod, ApiCategoryPricePeriodController $api): RedirectResponse
    {
        return $this->redirectToPeriodos($api->update($request, $categoryPricePeriod), (int) $request->input('category_id'), $request);
    }

    public function destroy(Request $request, CategoryPricePeriod $categoryPricePeriod, ApiCategoryPricePeriodController $api): RedirectResponse
    {
        return $this->redirectToPeriodos($api->destroy($categoryPricePeriod), (int) $request->input('category_id'), $request);
    }

    private function redirectToPeriodos($response, int $categoryId, Request $request): RedirectResponse
    {
        $url = route('admin.categorias.periodos.index', $categoryId).'?'.http_build_query(['evento_id' => $request->input('evento_id')]);
        $payload = $response->getData(true);

        if (!($payload['success'] ?? false)) {
            return redirect($url)->withErrors($this->publicExtractErrors($payload));
        }

        return redirect($url)->with('status', $payload['message'] ?? 'Operación realizada correctamente.');
    }

    private function publicExtractErrors(array $payload): array
    {
        $errors = $payload['errors'] ?? null;
        if (is_array($errors)) {
            return array_map(fn ($messages) => is_array($messages) ? implode(' ', $messages) : $messages, $errors);
        }

        return ['general' => $payload['error'] ?? 'Ocurrió un error.'];
    }
}
