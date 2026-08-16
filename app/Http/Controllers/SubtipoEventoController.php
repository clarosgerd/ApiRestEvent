<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreSubtipoEventoRequest;
use App\Http\Requests\UpdateSubtipoEventoRequest;
use App\Models\Evento;
use App\Models\SubtipoEvento;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * CRUD de administración del catálogo de subtipo de evento (15/08/2026) —
 * solo super_admin. Distinto de TipoEventoController::index() (público,
 * sin auth, solo activos, anidado) — ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
 */
class SubtipoEventoController extends Controller
{
    use AuthorizesEventoScope;

    public function index(): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => SubtipoEvento::with('tipoEvento')->orderBy('nombre')->get(),
        ]);
    }

    public function store(StoreSubtipoEventoRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        $data['activo'] = $data['activo'] ?? true;

        $subtipo = SubtipoEvento::create($data);

        AdminAuditLogger::log('create', 'SubtipoEvento', $subtipo->id, null, null, $subtipo->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Subtipo de evento creado correctamente.',
            'data' => $subtipo,
        ], 201);
    }

    public function update(UpdateSubtipoEventoRequest $request, SubtipoEvento $subtipoEvento): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $before = $subtipoEvento->toArray();
        $subtipoEvento->update($request->validated());

        AdminAuditLogger::log('update', 'SubtipoEvento', $subtipoEvento->id, null, $before, $subtipoEvento->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Subtipo de evento actualizado correctamente.',
            'data' => $subtipoEvento,
        ]);
    }

    public function destroy(SubtipoEvento $subtipoEvento): JsonResponse
    {
        $this->assertIsSuperAdmin();

        if (Evento::where('subtipo_evento_id', $subtipoEvento->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Este subtipo ya está en uso por algún evento — desactívelo en vez de eliminarlo.',
            ], 409);
        }

        $before = $subtipoEvento->toArray();
        $subtipoEvento->delete();

        AdminAuditLogger::log('delete', 'SubtipoEvento', $subtipoEvento->id, null, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Subtipo de evento eliminado correctamente.',
        ]);
    }
}
