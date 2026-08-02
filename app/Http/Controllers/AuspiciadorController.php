<?php

namespace App\Http\Controllers;

use App\Models\Auspiciador;
use App\Http\Requests\StoreAuspiciadorRequest;
use App\Http\Requests\UpdateAuspiciadorRequest;
use App\Http\Resources\AuspiciadorResource;
use App\Http\Resources\AuspiciadorCollection;
use App\Filters\AuspiciadorFilter;
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuspiciadorController extends Controller
{
    use AuthorizesEventoScope;

    public function index(Request $request)
    {
        $filter = new AuspiciadorFilter();
        $filterItems = $filter->transform($request);
        $auspiciador = Auspiciador::where($filterItems);
        return new AuspiciadorCollection($auspiciador->paginate()->appends($request->query()));
    }

    public function store(StoreAuspiciadorRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->assertCanWriteEvento((int) $data['event_id']);

        $auspiciador = Auspiciador::create($data);

        AdminAuditLogger::log('create', 'auspiciador', $auspiciador->id, (int) $auspiciador->event_id, null, $auspiciador->toArray());

        return response()->json([
            'success'     => true,
            'message'     => 'Auspiciador creado correctamente.',
            'auspiciador' => new AuspiciadorResource($auspiciador),
        ], 201);
    }

    public function show(Auspiciador $auspiciador)
    {
        return new AuspiciadorResource($auspiciador);
    }

    public function update(UpdateAuspiciadorRequest $request, Auspiciador $auspiciador): JsonResponse
    {
        $this->assertCanWriteEvento((int) $auspiciador->event_id);

        $before = $auspiciador->toArray();
        $auspiciador->update($request->validated());

        AdminAuditLogger::log('update', 'auspiciador', $auspiciador->id, (int) $auspiciador->event_id, $before, $auspiciador->toArray());

        return response()->json([
            'success'     => true,
            'message'     => 'Auspiciador actualizado correctamente.',
            'auspiciador' => new AuspiciadorResource($auspiciador),
        ]);
    }

    /**
     * Sin guarda de inscripciones: es un logo de carrusel, ningún
     * participante lo referencia.
     */
    public function destroy(Auspiciador $auspiciador): JsonResponse
    {
        $this->assertCanWriteEvento((int) $auspiciador->event_id);

        $before = $auspiciador->toArray();
        $auspiciador->delete();

        AdminAuditLogger::log('delete', 'auspiciador', $auspiciador->id, (int) $auspiciador->event_id, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Auspiciador eliminado correctamente.',
        ]);
    }
}
