<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreOrganizadorRequest;
use App\Http\Requests\UpdateOrganizadorRequest;
use App\Http\Resources\OrganizadorResource;
use App\Models\FormasPago;
use App\Models\Organizador;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /**
     * Formas de pago (19/08/2026) — ver
     * brain/PLAN-INTEGRACION-PAGO-MERU-19082026.md. Lista TODO lo que este
     * organizador podría seleccionar (catálogo del sistema + lo propio que
     * tuviera) junto con si ya lo tiene activo en el pivote
     * organizador_formas_pago — así el panel puede pintar los checkboxes
     * sin que el admin tenga que adivinar el estado actual.
     */
    public function formasPago(Organizador $organizador): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $disponibles = FormasPago::whereNull('organizador_id')
            ->orWhere('organizador_id', $organizador->id)
            ->orderBy('nombre')
            ->get();

        $seleccionadasIds = $organizador->formasPagoSeleccionadas()->pluck('formas_pagos.id')->all();

        return response()->json([
            'success' => true,
            'data' => $disponibles->map(fn (FormasPago $fp) => [
                'id' => $fp->id,
                'slug' => $fp->slug,
                'nombre' => $fp->nombre,
                'tipo' => $fp->tipo,
                'esDelSistema' => $fp->organizador_id === null,
                'seleccionada' => in_array($fp->id, $seleccionadasIds, true),
            ]),
            // Si el pivote está vacío, formasPagoEfectivas() usa por
            // default los métodos del sistema activos — informativo para
            // el panel (ver Organizador::formasPagoEfectivas()).
            'usandoDefaultDelSistema' => empty($seleccionadasIds),
        ]);
    }

    /**
     * Reemplaza la selección completa de este organizador — un
     * `forma_pago_ids` vacío es válido a propósito: significa "volver al
     * default del sistema" (ver formasPagoEfectivas()), no un error.
     */
    public function updateFormasPago(Request $request, Organizador $organizador): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $ids = collect($request->input('forma_pago_ids', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        // Solo se puede seleccionar del sistema o de lo propio del
        // organizador — no la forma de pago de otro organizador.
        $validasIds = FormasPago::whereIn('id', $ids)
            ->where(function ($q) use ($organizador) {
                $q->whereNull('organizador_id')->orWhere('organizador_id', $organizador->id);
            })
            ->pluck('id')
            ->all();

        $sync = collect($validasIds)->mapWithKeys(fn ($id) => [$id => ['activo' => true]])->all();
        $organizador->formasPagoSeleccionadas()->sync($sync);

        AdminAuditLogger::log('update', 'Organizador.formasPago', $organizador->id, null, null, ['forma_pago_ids' => $validasIds]);

        return response()->json([
            'success' => true,
            'message' => 'Formas de pago actualizadas correctamente.',
        ]);
    }
}
