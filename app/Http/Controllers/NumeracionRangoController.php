<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreNumeracionRangoRequest;
use App\Http\Requests\UpdateNumeracionRangoRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\NumeracionRango;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * CRUD de `numeracion_rangos` — ver plan "Aviso de numeración vs.
 * género/edad real en entrega de kit" (16/09/2026). Mismo patrón que
 * CategoryPricePeriodController: no hay index() propio, la lista de rangos
 * de una categoría ya viaja embebida en CategoryResource.numeracion_rangos
 * (GET /category/{id}), y cada respuesta devuelve la CategoryResource
 * completa para que admin-eventos no necesite un segundo round-trip.
 */
class NumeracionRangoController extends Controller
{
    use AuthorizesEventoScope;

    public function store(StoreNumeracionRangoRequest $request, Category $category): JsonResponse
    {
        $this->assertCanWriteEvento((int) $category->event_id);

        $data = $request->validated();

        $rango = $category->numeracionRangos()->create($data);

        AdminAuditLogger::log('create', 'numeracion_rango', $rango->id, (int) $category->event_id, null, $rango->toArray());

        return response()->json([
            'success'  => true,
            'message'  => 'Rango de numeración creado correctamente.',
            'category' => new CategoryResource($category->fresh()),
        ], 201);
    }

    public function update(UpdateNumeracionRangoRequest $request, NumeracionRango $numeracionRango): JsonResponse
    {
        $category = $numeracionRango->category;
        $this->assertCanWriteEvento((int) $category->event_id);

        $data = $request->validated();

        $before = $numeracionRango->toArray();
        $numeracionRango->update($data);

        AdminAuditLogger::log('update', 'numeracion_rango', $numeracionRango->id, (int) $category->event_id, $before, $numeracionRango->toArray());

        return response()->json([
            'success'  => true,
            'message'  => 'Rango de numeración actualizado correctamente.',
            'category' => new CategoryResource($category->fresh()),
        ]);
    }

    public function destroy(NumeracionRango $numeracionRango): JsonResponse
    {
        $category = $numeracionRango->category;
        $this->assertCanWriteEvento((int) $category->event_id);

        $before = $numeracionRango->toArray();
        $numeracionRango->delete();

        AdminAuditLogger::log('delete', 'numeracion_rango', $before['id'], (int) $category->event_id, $before, null);

        return response()->json([
            'success'  => true,
            'message'  => 'Rango de numeración eliminado correctamente.',
            'category' => new CategoryResource($category->fresh()),
        ]);
    }
}
