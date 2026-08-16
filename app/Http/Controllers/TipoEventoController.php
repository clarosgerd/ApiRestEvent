<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreTipoEventoRequest;
use App\Http\Requests\UpdateTipoEventoRequest;
use App\Models\Evento;
use App\Models\TipoEvento;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * Catálogo de disciplinas de evento (Carrera de Ruta, Trail Running,
 * Ciclismo, Caminata, Triatlón, Natación, más "Congreso / No aplica" para
 * eventos no deportivos) — lectura pública, sin datos sensibles. Usado por
 * admin-eventos para poblar los selects de tipo/subtipo al crear o editar
 * un evento. Ver brain/PLAN-ENDPOINT-CONSUMO-05082026.md.
 *
 * `adminIndex/store/update/destroy` agregados 15/08/2026 para el CRUD de
 * administración del catálogo (solo super_admin) — ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
 * Deliberadamente separados de `index()`: ese sigue siendo público, sin
 * auth, solo activos — no se toca, para no romper al formulario de alta/
 * edición de evento que ya lo consume.
 */
class TipoEventoController extends Controller
{
    use AuthorizesEventoScope;

    public function index(): JsonResponse
    {
        $tipos = TipoEvento::where('activo', true)
            ->with(['subtipos' => fn ($q) => $q->where('activo', true)->orderBy('nombre')])
            ->orderBy('nombre')
            ->get()
            ->map(fn (TipoEvento $tipo) => [
                'id' => $tipo->id,
                'nombre' => $tipo->nombre,
                'icono' => $tipo->icono,
                'subtipos' => $tipo->subtipos->map(fn ($sub) => [
                    'id' => $sub->id,
                    'nombre' => $sub->nombre,
                ]),
            ]);

        return response()->json([
            'success' => true,
            'tiposEvento' => $tipos,
        ]);
    }

    public function adminIndex(): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => TipoEvento::orderBy('nombre')->get(),
        ]);
    }

    public function store(StoreTipoEventoRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        $data['activo'] = $data['activo'] ?? true;

        $tipoEvento = TipoEvento::create($data);

        AdminAuditLogger::log('create', 'TipoEvento', $tipoEvento->id, null, null, $tipoEvento->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Tipo de evento creado correctamente.',
            'data' => $tipoEvento,
        ], 201);
    }

    public function update(UpdateTipoEventoRequest $request, TipoEvento $tipoEvento): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $before = $tipoEvento->toArray();
        $tipoEvento->update($request->validated());

        AdminAuditLogger::log('update', 'TipoEvento', $tipoEvento->id, null, $before, $tipoEvento->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Tipo de evento actualizado correctamente.',
            'data' => $tipoEvento,
        ]);
    }

    public function destroy(TipoEvento $tipoEvento): JsonResponse
    {
        $this->assertIsSuperAdmin();

        if ($tipoEvento->subtipos()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Este tipo de evento ya tiene subtipos asociados — desactívelo en vez de eliminarlo.',
            ], 409);
        }

        if (Evento::where('tipo_evento_id', $tipoEvento->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Este tipo de evento ya está en uso por algún evento — desactívelo en vez de eliminarlo.',
            ], 409);
        }

        $before = $tipoEvento->toArray();
        $tipoEvento->delete();

        AdminAuditLogger::log('delete', 'TipoEvento', $tipoEvento->id, null, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Tipo de evento eliminado correctamente.',
        ]);
    }
}
