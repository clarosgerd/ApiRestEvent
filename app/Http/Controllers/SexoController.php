<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreSexoRequest;
use App\Http\Requests\UpdateSexoRequest;
use App\Models\Category;
use App\Models\Sexo;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * CRUD del catálogo de sexo (15/08/2026) — solo super_admin, mismo
 * criterio que SocioController/OrganizadorController (config global, no
 * scoped por evento). Ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
 */
class SexoController extends Controller
{
    use AuthorizesEventoScope;

    public function index(): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => Sexo::orderBy('nombre')->get(),
        ]);
    }

    public function store(StoreSexoRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        $data['activo'] = $data['activo'] ?? true;

        $sexo = Sexo::create($data);

        AdminAuditLogger::log('create', 'Sexo', $sexo->id, null, null, $sexo->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Sexo creado correctamente.',
            'data' => $sexo,
        ], 201);
    }

    public function update(UpdateSexoRequest $request, Sexo $sexo): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $before = $sexo->toArray();
        $sexo->update($request->validated());

        AdminAuditLogger::log('update', 'Sexo', $sexo->id, null, $before, $sexo->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Sexo actualizado correctamente.',
            'data' => $sexo,
        ]);
    }

    public function destroy(Sexo $sexo): JsonResponse
    {
        $this->assertIsSuperAdmin();

        // categories.sexo_id no tiene FK real (ver migración de sexos) —
        // el chequeo es manual, no una relación Eloquent.
        if (Category::where('sexo_id', $sexo->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Este sexo ya está en uso por alguna categoría — desactívelo en vez de eliminarlo.',
            ], 409);
        }

        $before = $sexo->toArray();
        $sexo->delete();

        AdminAuditLogger::log('delete', 'Sexo', $sexo->id, null, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Sexo eliminado correctamente.',
        ]);
    }
}
