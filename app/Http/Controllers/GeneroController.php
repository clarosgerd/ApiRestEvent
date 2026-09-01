<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreGeneroRequest;
use App\Http\Requests\UpdateGeneroRequest;
use App\Models\Genero;
use App\Models\Participante;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * Catálogo de género de participante (31/08/2026) — ver
 * PLAN-GENERO-CATALOGO-CAMPOS-OPCIONALES-31082026.md. Mismo criterio de
 * split público/admin que TipoEventoController: `index()` es público, sin
 * auth, solo activos — lo consume el formulario de inscripción de
 * elascenso/event (antes tenía las opciones hardcodeadas en el HTML).
 * `adminIndex/store/update/destroy` son solo super_admin, para gestionar
 * el catálogo desde admin-eventos.
 */
class GeneroController extends Controller
{
    use AuthorizesEventoScope;

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Genero::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function adminIndex(): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => Genero::orderBy('nombre')->get(),
        ]);
    }

    public function store(StoreGeneroRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        $data['activo'] = $data['activo'] ?? true;

        $genero = Genero::create($data);

        AdminAuditLogger::log('create', 'Genero', $genero->id, null, null, $genero->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Género creado correctamente.',
            'data' => $genero,
        ], 201);
    }

    public function update(UpdateGeneroRequest $request, Genero $genero): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $before = $genero->toArray();
        $genero->update($request->validated());

        AdminAuditLogger::log('update', 'Genero', $genero->id, null, $before, $genero->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Género actualizado correctamente.',
            'data' => $genero,
        ]);
    }

    public function destroy(Genero $genero): JsonResponse
    {
        $this->assertIsSuperAdmin();

        // A diferencia de Sexo (sin FK real), acá sí importa: genero se
        // guarda como string suelto en participantes.genero (ENUM), no hay
        // FK que lo impida a nivel de base de datos.
        if (Participante::where('genero', $genero->nombre)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Este género ya está en uso por algún participante — desactívelo en vez de eliminarlo.',
            ], 409);
        }

        $before = $genero->toArray();
        $genero->delete();

        AdminAuditLogger::log('delete', 'Genero', $genero->id, null, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Género eliminado correctamente.',
        ]);
    }
}
