<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreTallerComboPrecioRequest;
use App\Http\Requests\UpdateTallerComboPrecioRequest;
use App\Http\Resources\TallerComboResource;
use App\Models\Category;
use App\Models\TallerCombo;
use App\Models\TallerComboPrecio;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * CRUD de precios por categoría de un combo de talleres (13/10/2026,
 * pedido del usuario) — el mismo combo (mismos talleres) puede costar
 * distinto según la categoría del participante. Mismo patrón que
 * TallerPricePeriodController: sin `index()` propio, la lista ya viaja
 * embebida en `TallerComboResource.precios` (GET /event/{evento}/taller-combos).
 */
class TallerComboPrecioController extends Controller
{
    use AuthorizesEventoScope;

    public function store(StoreTallerComboPrecioRequest $request, TallerCombo $tallerCombo): JsonResponse
    {
        $this->assertCanWriteEvento((int) $tallerCombo->evento_id);

        $data = $request->validated();

        if ($error = $this->rechazaSiCategoriaAjenaAlEvento($tallerCombo, $data['category_id'] ?? null)) {
            return $error;
        }
        if ($error = $this->rechazaSiYaExisteFilaParaEstaCategoria($tallerCombo, $data['category_id'] ?? null)) {
            return $error;
        }

        $precio = $tallerCombo->precios()->create($data);

        AdminAuditLogger::log('create', 'taller_combo_precio', $precio->id, (int) $tallerCombo->evento_id, null, $precio->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Precio del combo creado correctamente.',
            'data'    => new TallerComboResource($tallerCombo->load(['talleres', 'precios.category'])),
        ], 201);
    }

    public function update(UpdateTallerComboPrecioRequest $request, TallerComboPrecio $tallerComboPrecio): JsonResponse
    {
        $tallerCombo = $tallerComboPrecio->tallerCombo;
        $this->assertCanWriteEvento((int) $tallerCombo->evento_id);

        $data = $request->validated();

        if ($error = $this->rechazaSiCategoriaAjenaAlEvento($tallerCombo, $data['category_id'] ?? null)) {
            return $error;
        }
        if ($error = $this->rechazaSiYaExisteFilaParaEstaCategoria($tallerCombo, $data['category_id'] ?? null, excluir: $tallerComboPrecio->id)) {
            return $error;
        }

        $before = $tallerComboPrecio->toArray();
        $tallerComboPrecio->update($data);

        AdminAuditLogger::log('update', 'taller_combo_precio', $tallerComboPrecio->id, (int) $tallerCombo->evento_id, $before, $tallerComboPrecio->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Precio del combo actualizado correctamente.',
            'data'    => new TallerComboResource($tallerCombo->load(['talleres', 'precios.category'])),
        ]);
    }

    public function destroy(TallerComboPrecio $tallerComboPrecio): JsonResponse
    {
        $tallerCombo = $tallerComboPrecio->tallerCombo;
        $this->assertCanWriteEvento((int) $tallerCombo->evento_id);

        $before = $tallerComboPrecio->toArray();
        $tallerComboPrecio->delete();

        AdminAuditLogger::log('delete', 'taller_combo_precio', $before['id'], (int) $tallerCombo->evento_id, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Precio del combo eliminado correctamente.',
            'data'    => new TallerComboResource($tallerCombo->load(['talleres', 'precios.category'])),
        ]);
    }

    private function rechazaSiCategoriaAjenaAlEvento(TallerCombo $tallerCombo, ?int $categoryId): ?JsonResponse
    {
        if ($categoryId === null) {
            return null;
        }

        $categoria = Category::find($categoryId);
        if (! $categoria || (int) $categoria->event_id !== (int) $tallerCombo->evento_id) {
            return response()->json([
                'success' => false,
                'error'   => 'Esa categoría no pertenece al evento de este combo.',
            ], 422);
        }

        return null;
    }

    /**
     * A lo sumo una fila por categoría (o default, category_id=null) por
     * combo — MySQL no lo garantiza solo (NULL es distinto de NULL en una
     * UNIQUE KEY), así que se valida acá.
     */
    private function rechazaSiYaExisteFilaParaEstaCategoria(TallerCombo $tallerCombo, ?int $categoryId, ?int $excluir = null): ?JsonResponse
    {
        $query = TallerComboPrecio::where('taller_combo_id', $tallerCombo->id)
            ->where('category_id', $categoryId);

        if ($excluir !== null) {
            $query->where('id', '!=', $excluir);
        }

        if ($query->exists()) {
            $etiqueta = $categoryId === null ? 'el precio default' : 'esta categoría';

            return response()->json([
                'success' => false,
                'error'   => "Este combo ya tiene {$etiqueta} cargado — editá esa fila en vez de crear otra.",
            ], 422);
        }

        return null;
    }
}
