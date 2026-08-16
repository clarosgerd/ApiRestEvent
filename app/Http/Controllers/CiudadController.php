<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreCiudadRequest;
use App\Http\Requests\UpdateCiudadRequest;
use App\Models\Ciudad;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * CRUD del catálogo de ciudad (15/08/2026) — solo super_admin. Ver
 * PaisController (mismo criterio) y
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
 */
class CiudadController extends Controller
{
    use AuthorizesEventoScope;

    public function index(): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => Ciudad::with('pais')->orderBy('nombre')->get(),
        ]);
    }

    public function store(StoreCiudadRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        $data['activo'] = $data['activo'] ?? true;

        $ciudad = Ciudad::create($data);

        AdminAuditLogger::log('create', 'Ciudad', $ciudad->id, null, null, $ciudad->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Ciudad creada correctamente.',
            'data' => $ciudad,
        ], 201);
    }

    public function update(UpdateCiudadRequest $request, Ciudad $ciudad): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $before = $ciudad->toArray();
        $ciudad->update($request->validated());

        AdminAuditLogger::log('update', 'Ciudad', $ciudad->id, null, $before, $ciudad->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Ciudad actualizada correctamente.',
            'data' => $ciudad,
        ]);
    }

    public function destroy(Ciudad $ciudad): JsonResponse
    {
        $this->assertIsSuperAdmin();

        if ($ciudad->organizadores()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Esta ciudad ya tiene organizadores asociados — desactívela en vez de eliminarla.',
            ], 409);
        }

        $before = $ciudad->toArray();
        $ciudad->delete();

        AdminAuditLogger::log('delete', 'Ciudad', $ciudad->id, null, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Ciudad eliminada correctamente.',
        ]);
    }
}
