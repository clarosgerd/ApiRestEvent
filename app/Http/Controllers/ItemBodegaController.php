<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\AsignarItemBodegaRequest;
use App\Http\Requests\StoreItemBodegaRequest;
use App\Http\Requests\UpdateItemBodegaRequest;
use App\Http\Resources\ItemBodegaResource;
use App\Http\Resources\SouvenirResource;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\ItemBodega;
use App\Models\Souvenir;
use Illuminate\Http\JsonResponse;

/**
 * Bodega de stock por evento — ver PLAN-BODEGA-STOCK-EVENTO-14082026.md.
 * Mismo scoping que SouvenirController/ItemStockController (admin de su
 * propio evento, o super_admin).
 */
class ItemBodegaController extends Controller
{
    use AuthorizesEventoScope;

    /**
     * Catálogo del evento + resumen agregado (asignaciones por
     * form_type, con su cupo/disponible propio).
     */
    public function index(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento((int) $event->id);

        $items = ItemBodega::where('evento_id', $event->id)
            ->with('asignaciones.formType')
            ->get();

        return response()->json([
            'success'      => true,
            'item_bodega'  => ItemBodegaResource::collection($items),
        ]);
    }

    public function store(StoreItemBodegaRequest $request, Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento((int) $event->id);

        $item = ItemBodega::create($request->validated() + ['evento_id' => $event->id]);

        return response()->json([
            'success'     => true,
            'message'     => 'Ítem de bodega creado correctamente.',
            'item_bodega' => new ItemBodegaResource($item),
        ], 201);
    }

    public function update(UpdateItemBodegaRequest $request, ItemBodega $itemBodega): JsonResponse
    {
        $this->assertCanWriteEvento((int) $itemBodega->evento_id);

        $itemBodega->update($request->validated());

        return response()->json([
            'success'     => true,
            'message'     => 'Ítem de bodega actualizado correctamente.',
            'item_bodega' => new ItemBodegaResource($itemBodega),
        ]);
    }

    /**
     * No borra en cascada las asignaciones (Souvenir) — solo se
     * desvinculan (nullOnDelete) y siguen operando standalone con su
     * propio stock/precio, ver la migración.
     */
    public function destroy(ItemBodega $itemBodega): JsonResponse
    {
        $this->assertCanWriteEvento((int) $itemBodega->evento_id);

        $itemBodega->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ítem de bodega eliminado correctamente.',
        ]);
    }

    /**
     * Asignar el ítem a un form_type — crea un `Souvenir` nuevo (la
     * asignación) copiando la identidad del ítem de bodega. price=0,
     * incluido=false por defecto: son términos comerciales de esa
     * asignación puntual, el organizador los ajusta después junto con su
     * propio cupo en la pantalla de stock existente (souvenirs/{id}/stock).
     */
    public function asignar(AsignarItemBodegaRequest $request, ItemBodega $itemBodega): JsonResponse
    {
        $this->assertCanWriteEvento((int) $itemBodega->evento_id);

        $formType = FormType::findOrFail($request->validated('form_types_id'));

        if ((int) $formType->event_id !== (int) $itemBodega->evento_id) {
            return response()->json([
                'success' => false,
                'error'   => 'Ese tipo de inscripción no pertenece al mismo evento que la bodega.',
            ], 422);
        }

        $yaAsignado = Souvenir::where('item_bodega_id', $itemBodega->id)
            ->where('form_types_id', $formType->id)
            ->exists();

        if ($yaAsignado) {
            return response()->json([
                'success' => false,
                'error'   => 'Este ítem ya está asignado a ese tipo de inscripción.',
            ], 422);
        }

        $souvenir = Souvenir::create([
            'form_types_id'   => $formType->id,
            'item_bodega_id'  => $itemBodega->id,
            'name'            => $itemBodega->nombre,
            // `souvenirs.icon` es NOT NULL (a diferencia de `item_bodega.icon`,
            // que sí permite quedar vacío) — se cae a '' si el ítem de
            // bodega todavía no tiene ícono cargado.
            'icon'            => $itemBodega->icon ?? '',
            'foto_url'        => $itemBodega->foto_url,
            'requiere_talla'  => $itemBodega->requiere_talla,
            'requiere_sexo'   => $itemBodega->requiere_sexo,
            'price'           => 0,
            'incluido'        => false,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Ítem asignado correctamente — cargá su precio y stock propio.',
            'souvenir' => new SouvenirResource($souvenir),
        ], 201);
    }
}
