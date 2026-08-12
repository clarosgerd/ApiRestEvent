<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreCategoryPricePeriodRequest;
use App\Http\Requests\UpdateCategoryPricePeriodRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\CategoryPricePeriod;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * CRUD de `category_price_periods` — ver PRD-precios-periodos-fechas.md.
 * Mismo scoping que Souvenir/ItemStock (admin de su propio evento, o
 * super_admin). No hay `index()` propio: la lista de períodos de una
 * categoría ya viaja embebida en `CategoryResource.periodos`
 * (GET /category/{id}), así que un listado separado sería redundante.
 *
 * Devuelve la `CategoryResource` completa (no solo el período) en cada
 * respuesta — admin-eventos necesita el `precio_vigente` recalculado
 * inmediatamente después de escribir, sin un segundo round-trip.
 */
class CategoryPricePeriodController extends Controller
{
    use AuthorizesEventoScope;

    public function store(StoreCategoryPricePeriodRequest $request, Category $category): JsonResponse
    {
        $this->assertCanWriteEvento((int) $category->event_id);

        $data = $request->validated();

        if ($error = $this->rechazaSiSePisaConOtro($category, $data)) {
            return $error;
        }

        $periodo = $category->pricePeriods()->create($data);

        AdminAuditLogger::log('create', 'categoria_periodo', $periodo->id, (int) $category->event_id, null, $periodo->toArray());

        return response()->json([
            'success'  => true,
            'message'  => 'Período de precio creado correctamente.',
            'category' => new CategoryResource($category->fresh('pricePeriods')),
        ], 201);
    }

    public function update(UpdateCategoryPricePeriodRequest $request, CategoryPricePeriod $categoryPricePeriod): JsonResponse
    {
        $category = $categoryPricePeriod->category;
        $this->assertCanWriteEvento((int) $category->event_id);

        $data = $request->validated();

        if ($error = $this->rechazaSiSePisaConOtro($category, $data, excluir: $categoryPricePeriod->id)) {
            return $error;
        }

        $before = $categoryPricePeriod->toArray();
        $categoryPricePeriod->update($data);

        AdminAuditLogger::log('update', 'categoria_periodo', $categoryPricePeriod->id, (int) $category->event_id, $before, $categoryPricePeriod->toArray());

        return response()->json([
            'success'  => true,
            'message'  => 'Período de precio actualizado correctamente.',
            'category' => new CategoryResource($category->fresh('pricePeriods')),
        ]);
    }

    public function destroy(CategoryPricePeriod $categoryPricePeriod): JsonResponse
    {
        $category = $categoryPricePeriod->category;
        $this->assertCanWriteEvento((int) $category->event_id);

        $before = $categoryPricePeriod->toArray();
        $categoryPricePeriod->delete();

        AdminAuditLogger::log('delete', 'categoria_periodo', $before['id'], (int) $category->event_id, $before, null);

        return response()->json([
            'success'  => true,
            'message'  => 'Período de precio eliminado correctamente.',
            'category' => new CategoryResource($category->fresh('pricePeriods')),
        ]);
    }

    /**
     * Rechaza con 422 si el rango [fecha_desde, fecha_hasta] se pisa con
     * otro período ya cargado de la misma categoría. Chequeo estándar de
     * overlap: NOT (existente.fecha_hasta < nuevo.fecha_desde OR
     * existente.fecha_desde > nuevo.fecha_hasta) — ver PRD, sección 1.
     * `$excluir` es el propio período en un update (no se compara contra
     * sí mismo).
     */
    private function rechazaSiSePisaConOtro(Category $category, array $data, ?int $excluir = null): ?JsonResponse
    {
        $query = CategoryPricePeriod::where('category_id', $category->id)
            ->where('fecha_hasta', '>=', $data['fecha_desde'])
            ->where('fecha_desde', '<=', $data['fecha_hasta']);

        if ($excluir !== null) {
            $query->where('id', '!=', $excluir);
        }

        if ($query->exists()) {
            return response()->json([
                'success' => false,
                'error'   => 'Este rango de fechas se pisa con otro período ya cargado para esta categoría.',
            ], 422);
        }

        return null;
    }
}
