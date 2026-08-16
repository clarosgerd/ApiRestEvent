<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StorePaisRequest;
use App\Http\Requests\UpdatePaisRequest;
use App\Models\Pais;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * CRUD del catálogo de país (15/08/2026) — solo super_admin. La tabla y el
 * modelo ya existían (20/07/2026, usados hoy por Organizador/Ciudad) pero
 * sin ninguna pantalla de administración. Ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
 */
class PaisController extends Controller
{
    use AuthorizesEventoScope;

    public function index(): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => Pais::orderBy('nombre')->get(),
        ]);
    }

    public function store(StorePaisRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        $data['activo'] = $data['activo'] ?? true;

        $pais = Pais::create($data);

        AdminAuditLogger::log('create', 'Pais', $pais->id, null, null, $pais->toArray());

        return response()->json([
            'success' => true,
            'message' => 'País creado correctamente.',
            'data' => $pais,
        ], 201);
    }

    public function update(UpdatePaisRequest $request, Pais $pais): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $before = $pais->toArray();
        $pais->update($request->validated());

        AdminAuditLogger::log('update', 'Pais', $pais->id, null, $before, $pais->toArray());

        return response()->json([
            'success' => true,
            'message' => 'País actualizado correctamente.',
            'data' => $pais,
        ]);
    }

    public function destroy(Pais $pais): JsonResponse
    {
        $this->assertIsSuperAdmin();

        if ($pais->ciudades()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Este país ya tiene ciudades asociadas — desactívelo en vez de eliminarlo.',
            ], 409);
        }

        if ($pais->organizadores()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Este país ya tiene organizadores asociados — desactívelo en vez de eliminarlo.',
            ], 409);
        }

        $before = $pais->toArray();
        $pais->delete();

        AdminAuditLogger::log('delete', 'Pais', $pais->id, null, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'País eliminado correctamente.',
        ]);
    }
}
