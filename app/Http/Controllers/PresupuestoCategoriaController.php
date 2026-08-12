<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StorePresupuestoCategoriaRequest;
use App\Http\Requests\UpdatePresupuestoCategoriaRequest;
use App\Models\PresupuestoCategoria;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * Catálogo de rubros del presupuesto (Marketing, Logística, Premios...) —
 * solo super_admin, mismo patrón que SocioController. Ver
 * PRD-presupuesto_de_un_evento.md.
 */
class PresupuestoCategoriaController extends Controller
{
    use AuthorizesEventoScope;

    public function index(): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => PresupuestoCategoria::orderBy('tipo')->orderBy('nombre')->get(),
        ]);
    }

    public function store(StorePresupuestoCategoriaRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        $data['activo'] = $data['activo'] ?? true;

        $categoria = PresupuestoCategoria::create($data);

        AdminAuditLogger::log('create', 'PresupuestoCategoria', $categoria->id, null, null, $categoria->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Categoría creada correctamente.',
            'data' => $categoria,
        ], 201);
    }

    public function update(UpdatePresupuestoCategoriaRequest $request, PresupuestoCategoria $categoria): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $before = $categoria->toArray();
        $categoria->update($request->validated());

        AdminAuditLogger::log('update', 'PresupuestoCategoria', $categoria->id, null, $before, $categoria->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Categoría actualizada correctamente.',
            'data' => $categoria,
        ]);
    }

    public function destroy(PresupuestoCategoria $categoria): JsonResponse
    {
        $this->assertIsSuperAdmin();

        // No se borra si ya tiene movimientos asociados — mismo criterio
        // que SocioController::destroy (el historial financiero no debe
        // poder desaparecer). Desactivarla en vez de borrarla la sacan de
        // las opciones para movimientos nuevos.
        if ($categoria->movimientos()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Esta categoría ya tiene movimientos registrados — desactívela en vez de eliminarla.',
            ], 409);
        }

        $before = $categoria->toArray();
        $categoria->delete();

        AdminAuditLogger::log('delete', 'PresupuestoCategoria', $categoria->id, null, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada correctamente.',
        ]);
    }
}
