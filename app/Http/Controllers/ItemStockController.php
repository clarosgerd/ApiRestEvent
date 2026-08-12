<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreItemStockRequest;
use App\Http\Requests\UpdateItemStockRequest;
use App\Http\Resources\ItemStockResource;
use App\Models\ItemStock;
use App\Models\Souvenir;
use Illuminate\Http\JsonResponse;

/**
 * Stock por talla×sexo de un ítem del kit — ver
 * PRD-kit-tallas-stock-lista-espera.md. Mismo scoping que
 * SouvenirController (admin de su propio evento, o super_admin).
 */
class ItemStockController extends Controller
{
    use AuthorizesEventoScope;

    public function index(Souvenir $souvenir): JsonResponse
    {
        $this->assertCanWriteEvento((int) $souvenir->formType->event_id);

        return response()->json([
            'success' => true,
            'stock'   => ItemStockResource::collection($souvenir->stock()->get()),
        ]);
    }

    public function store(StoreItemStockRequest $request, Souvenir $souvenir): JsonResponse
    {
        $this->assertCanWriteEvento((int) $souvenir->formType->event_id);

        $data = $request->validated();

        // La unique de la tabla no atrapa 2 filas talla=null/sexo=null
        // para el mismo souvenir (MySQL trata NULL como distinto de
        // NULL) — se valida acá.
        $yaExiste = ItemStock::where('souvenir_id', $souvenir->id)
            ->where('talla', $data['talla'] ?? null)
            ->where('sexo', $data['sexo'] ?? null)
            ->exists();

        if ($yaExiste) {
            return response()->json([
                'success' => false,
                'error'   => 'Ya existe una fila de stock para esa combinación de talla/sexo.',
            ], 422);
        }

        $stock = $souvenir->stock()->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Stock creado correctamente.',
            'stock'   => new ItemStockResource($stock),
        ], 201);
    }

    public function update(UpdateItemStockRequest $request, ItemStock $itemStock): JsonResponse
    {
        $this->assertCanWriteEvento((int) $itemStock->souvenir->formType->event_id);

        $itemStock->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Stock actualizado correctamente.',
            'stock'   => new ItemStockResource($itemStock),
        ]);
    }

    public function destroy(ItemStock $itemStock): JsonResponse
    {
        $this->assertCanWriteEvento((int) $itemStock->souvenir->formType->event_id);

        $itemStock->delete();

        return response()->json([
            'success' => true,
            'message' => 'Stock eliminado correctamente.',
        ]);
    }
}
