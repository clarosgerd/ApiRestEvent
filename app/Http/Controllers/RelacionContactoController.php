<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreRelacionContactoRequest;
use App\Http\Requests\UpdateRelacionContactoRequest;
use App\Models\RelacionContacto;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * CRUD del catálogo de relación del contacto de emergencia (15/08/2026) —
 * solo super_admin. Aditivo: no está relacionado con
 * `contacto_emergencia_participantes.relacion` (texto libre) en esta
 * sesión, ver elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
 */
class RelacionContactoController extends Controller
{
    use AuthorizesEventoScope;

    public function index(): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => RelacionContacto::orderBy('nombre')->get(),
        ]);
    }

    public function store(StoreRelacionContactoRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        $data['activo'] = $data['activo'] ?? true;

        $relacion = RelacionContacto::create($data);

        AdminAuditLogger::log('create', 'RelacionContacto', $relacion->id, null, null, $relacion->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Relación de contacto creada correctamente.',
            'data' => $relacion,
        ], 201);
    }

    public function update(UpdateRelacionContactoRequest $request, RelacionContacto $relacionContacto): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $before = $relacionContacto->toArray();
        $relacionContacto->update($request->validated());

        AdminAuditLogger::log('update', 'RelacionContacto', $relacionContacto->id, null, $before, $relacionContacto->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Relación de contacto actualizada correctamente.',
            'data' => $relacionContacto,
        ]);
    }

    public function destroy(RelacionContacto $relacionContacto): JsonResponse
    {
        $this->assertIsSuperAdmin();

        // Sin FK real que la use todavía (ver nota de la migración) — se
        // puede borrar libremente mientras no tenga dependientes futuros.
        $before = $relacionContacto->toArray();
        $relacionContacto->delete();

        AdminAuditLogger::log('delete', 'RelacionContacto', $relacionContacto->id, null, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Relación de contacto eliminada correctamente.',
        ]);
    }
}
