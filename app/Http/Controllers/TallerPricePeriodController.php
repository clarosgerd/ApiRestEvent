<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreTallerPricePeriodRequest;
use App\Http\Requests\UpdateTallerPricePeriodRequest;
use App\Http\Resources\TallerResource;
use App\Models\Taller;
use App\Models\TallerPricePeriod;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * CRUD de `taller_price_periods` (12/10/2026) — copia exacta del patrón de
 * CategoryPricePeriodController, aplicado a talleres. Sin `index()` propio:
 * la lista de períodos de un taller ya viaja embebida en
 * `TallerResource.periodos` (GET /event/{evento}/talleres/{taller}).
 *
 * Devuelve la `TallerResource` completa (no solo el período) en cada
 * respuesta — admin-eventos necesita el `precioVigente` recalculado
 * inmediatamente después de escribir, sin un segundo round-trip.
 */
class TallerPricePeriodController extends Controller
{
    use AuthorizesEventoScope;

    public function store(StoreTallerPricePeriodRequest $request, Taller $taller): JsonResponse
    {
        $this->assertCanWriteEvento((int) $taller->evento_id);

        $data = $request->validated();

        if ($error = $this->rechazaSiSePisaConOtro($taller, $data)) {
            return $error;
        }

        $periodo = $taller->pricePeriods()->create($data);

        AdminAuditLogger::log('create', 'taller_periodo', $periodo->id, (int) $taller->evento_id, null, $periodo->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Período de precio creado correctamente.',
            'data'    => new TallerResource($taller->fresh('pricePeriods')),
        ], 201);
    }

    public function update(UpdateTallerPricePeriodRequest $request, TallerPricePeriod $tallerPricePeriod): JsonResponse
    {
        $taller = $tallerPricePeriod->taller;
        $this->assertCanWriteEvento((int) $taller->evento_id);

        $data = $request->validated();

        if ($error = $this->rechazaSiSePisaConOtro($taller, $data, excluir: $tallerPricePeriod->id)) {
            return $error;
        }

        $before = $tallerPricePeriod->toArray();
        $tallerPricePeriod->update($data);

        AdminAuditLogger::log('update', 'taller_periodo', $tallerPricePeriod->id, (int) $taller->evento_id, $before, $tallerPricePeriod->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Período de precio actualizado correctamente.',
            'data'    => new TallerResource($taller->fresh('pricePeriods')),
        ]);
    }

    public function destroy(TallerPricePeriod $tallerPricePeriod): JsonResponse
    {
        $taller = $tallerPricePeriod->taller;
        $this->assertCanWriteEvento((int) $taller->evento_id);

        $before = $tallerPricePeriod->toArray();
        $tallerPricePeriod->delete();

        AdminAuditLogger::log('delete', 'taller_periodo', $before['id'], (int) $taller->evento_id, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Período de precio eliminado correctamente.',
            'data'    => new TallerResource($taller->fresh('pricePeriods')),
        ]);
    }

    /**
     * Mismo chequeo de overlap que CategoryPricePeriodController, aplicado
     * por taller.
     */
    private function rechazaSiSePisaConOtro(Taller $taller, array $data, ?int $excluir = null): ?JsonResponse
    {
        $query = TallerPricePeriod::where('taller_id', $taller->id)
            ->where('fecha_hasta', '>=', $data['fecha_desde'])
            ->where('fecha_desde', '<=', $data['fecha_hasta']);

        if ($excluir !== null) {
            $query->where('id', '!=', $excluir);
        }

        if ($query->exists()) {
            return response()->json([
                'success' => false,
                'error'   => 'Este rango de fechas se pisa con otro período ya cargado para este taller.',
            ], 422);
        }

        return null;
    }
}
