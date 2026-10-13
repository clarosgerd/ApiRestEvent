<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreTallerComboRequest;
use App\Http\Requests\UpdateTallerComboRequest;
use App\Http\Resources\TallerComboResource;
use App\Models\Evento;
use App\Models\Taller;
use App\Models\TallerCombo;
use App\Services\AdminAuditLogger;
use App\Support\Taller\ValidarSeleccionesTallerAction;
use Illuminate\Http\JsonResponse;

/**
 * CRUD de combos de talleres (12/10/2026, pedido del usuario) — "Taller A +
 * Taller B por el precio de 1" o "por $300 fijo". Mismo criterio de
 * scoping que TallerCongresoController: admin scoped a su propio evento, o
 * super_admin.
 */
class TallerComboController extends Controller
{
    use AuthorizesEventoScope;

    public function index(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        return response()->json([
            'success' => true,
            'data'    => TallerComboResource::collection(
                TallerCombo::where('evento_id', $event->id)
                    ->with(['talleres', 'precios.category'])
                    ->orderBy('id')
                    ->get()
            ),
        ]);
    }

    public function store(StoreTallerComboRequest $request, Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        $data = $request->validated();

        if ($error = $this->rechazaSiTallerAjenoAlEvento($event, $data['taller_ids'])) {
            return $error;
        }
        if ($error = $this->rechazaSiHorarioIncompatible($data['taller_ids'])) {
            return $error;
        }

        $combo = TallerCombo::create([
            'evento_id'   => $event->id,
            'nombre'      => $data['nombre'],
            'fecha_desde' => $data['fecha_desde'] ?? null,
            'fecha_hasta' => $data['fecha_hasta'] ?? null,
            'activo'      => $data['activo'] ?? true,
        ]);
        $combo->talleres()->sync($data['taller_ids']);

        AdminAuditLogger::log('create', 'taller_combo', $combo->id, $event->id, null, $combo->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Combo de talleres creado correctamente. Cargale el precio por categoría en /taller-combo/{id}/precios.',
            'data'    => new TallerComboResource($combo->load(['talleres', 'precios.category'])),
        ], 201);
    }

    public function update(UpdateTallerComboRequest $request, TallerCombo $tallerCombo): JsonResponse
    {
        $this->assertCanWriteEvento((int) $tallerCombo->evento_id);

        $data = $request->validated();

        $event = Evento::find($tallerCombo->evento_id);
        if ($error = $this->rechazaSiTallerAjenoAlEvento($event, $data['taller_ids'])) {
            return $error;
        }
        if ($error = $this->rechazaSiHorarioIncompatible($data['taller_ids'])) {
            return $error;
        }

        $before = $tallerCombo->toArray();
        $tallerCombo->update([
            'nombre'      => $data['nombre'],
            'fecha_desde' => $data['fecha_desde'] ?? null,
            'fecha_hasta' => $data['fecha_hasta'] ?? null,
            'activo'      => $data['activo'] ?? $tallerCombo->activo,
        ]);
        $tallerCombo->talleres()->sync($data['taller_ids']);

        AdminAuditLogger::log('update', 'taller_combo', $tallerCombo->id, (int) $tallerCombo->evento_id, $before, $tallerCombo->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Combo de talleres actualizado correctamente.',
            'data'    => new TallerComboResource($tallerCombo->load(['talleres', 'precios.category'])),
        ]);
    }

    public function destroy(TallerCombo $tallerCombo): JsonResponse
    {
        $this->assertCanWriteEvento((int) $tallerCombo->evento_id);

        $before = $tallerCombo->toArray();
        // nullOnDelete en participante_taller_sesion.taller_combo_id — las
        // filas ya facturadas con este combo no se rompen, solo pierden la
        // referencia (su discount/total persistido no cambia).
        $tallerCombo->delete();

        AdminAuditLogger::log('delete', 'taller_combo', $before['id'], (int) $before['evento_id'], $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Combo de talleres eliminado correctamente.',
        ]);
    }

    private function rechazaSiTallerAjenoAlEvento(Evento $event, array $tallerIds): ?JsonResponse
    {
        $pertenecen = Taller::where('evento_id', $event->id)->whereIn('id', $tallerIds)->count();

        if ($pertenecen !== count($tallerIds)) {
            return response()->json([
                'success' => false,
                'error'   => 'Todos los talleres del combo tienen que pertenecer a este evento.',
            ], 422);
        }

        return null;
    }

    /**
     * Elegibilidad por horario (12/10/2026, ver plan de combos de
     * talleres) — rechaza un combo cuyos talleres nunca podrían
     * seleccionarse juntos porque TODAS sus sesiones se solapan entre sí.
     * Chequeo pairwise (no combinatoria completa de N talleres): por cada
     * par de talleres del combo, tiene que existir al menos un par de
     * sesiones (una de cada uno) que NO se solape. Si alguno de los dos
     * todavía no tiene ninguna sesión cargada, se omite ese par (no hay
     * nada que comparar todavía — no bloquea un combo en armado).
     */
    private function rechazaSiHorarioIncompatible(array $tallerIds): ?JsonResponse
    {
        $talleres = Taller::with('sesiones')->whereIn('id', $tallerIds)->get();

        $n = $talleres->count();
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $sesionesA = $talleres[$i]->sesiones;
                $sesionesB = $talleres[$j]->sesiones;

                if ($sesionesA->isEmpty() || $sesionesB->isEmpty()) {
                    continue;
                }

                $hayCompatible = false;
                foreach ($sesionesA as $a) {
                    foreach ($sesionesB as $b) {
                        if (! ValidarSeleccionesTallerAction::seSolapan($a, $b)) {
                            $hayCompatible = true;
                            break 2;
                        }
                    }
                }

                if (! $hayCompatible) {
                    return response()->json([
                        'success' => false,
                        'error'   => "Los talleres '{$talleres[$i]->nombre}' y '{$talleres[$j]->nombre}' no tienen ningún horario compatible entre sí — nunca se podrían seleccionar juntos.",
                    ], 422);
                }
            }
        }

        return null;
    }
}
