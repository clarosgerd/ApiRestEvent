<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreOrganizadorRequest;
use App\Http\Requests\UpdateOrganizadorRequest;
use App\Http\Resources\OrganizadorResource;
use App\Models\Organizador;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * CRUD de organizadores (entidad de negocio dueña de uno o varios eventos —
 * ver Organizador::eventos()) — solo super_admin, mismo criterio que
 * SocioController (config/catálogo global, no scoped por evento). Antes el
 * modelo existía (creado junto con `eventos.organizador_id`, ver migración
 * 2026_07_20_200004) pero nunca tuvo su propio CRUD — todo evento nuevo
 * quedaba pegado al organizador id=1 por default (ver CrearEventoAction).
 */
class OrganizadorController extends Controller
{
    use AuthorizesEventoScope;

    public function index(): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $organizadores = Organizador::withCount('eventos')->orderBy('razon_social')->get();

        return response()->json([
            'success' => true,
            'data' => OrganizadorResource::collection($organizadores),
        ]);
    }

    public function show(Organizador $organizador): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data' => new OrganizadorResource($organizador->loadCount('eventos')),
        ]);
    }

    public function store(StoreOrganizadorRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        $data['activo'] = $data['activo'] ?? true;

        $organizador = Organizador::create($data);

        AdminAuditLogger::log('create', 'Organizador', $organizador->id, null, null, $organizador->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Organizador creado correctamente.',
            'data' => new OrganizadorResource($organizador),
        ], 201);
    }

    public function update(UpdateOrganizadorRequest $request, Organizador $organizador): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $before = $organizador->toArray();
        $organizador->update($request->validated());

        AdminAuditLogger::log('update', 'Organizador', $organizador->id, null, $before, $organizador->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Organizador actualizado correctamente.',
            'data' => new OrganizadorResource($organizador),
        ]);
    }

    public function destroy(Organizador $organizador): JsonResponse
    {
        $this->assertIsSuperAdmin();

        // No se borra un organizador con eventos asociados (ver
        // eventos.organizador_id, sin cascade) — perdería el vínculo de
        // facturación/convenio de eventos que ya existen. Desactivarlo en
        // cambio lo saca de los selects de "nuevo evento" sin romper nada
        // de lo ya creado.
        if ($organizador->eventos()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Este organizador ya tiene eventos asociados — desactívelo en vez de eliminarlo.',
            ], 409);
        }

        $before = $organizador->toArray();
        $organizador->delete();

        AdminAuditLogger::log('delete', 'Organizador', $organizador->id, null, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Organizador eliminado correctamente.',
        ]);
    }
}
