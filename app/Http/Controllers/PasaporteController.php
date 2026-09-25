<?php

namespace App\Http\Controllers;

use App\Actions\RealizarSorteoAction;
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Models\EmpresaExpositora;
use App\Models\Evento;
use App\Models\LeadCapturado;
use App\Models\Sorteo;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * SmartStand fase 4 — "Pasaporte médico": sorteo general del congreso entre los
 * asistentes que fueron capturados por al menos N stands DISTINTOS (N configurable
 * por evento en expositores_config.pasaporte_min_stands, 5 por defecto). Lo hace
 * el ORGANIZADOR (guard `admins`); cada sorteo queda en la auditoría.
 */
class PasaporteController extends Controller
{
    use AuthorizesEventoScope;

    public const MIN_STANDS_DEFAULT = 5;

    public function show(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento((int) $event->id);

        $minStands = $this->minStands($event);
        $visitas = $this->standsPorAsistente($event);

        // Cuántos asistentes visitaron k stands (k -> cuántos), para elegir N con datos.
        $distribucion = $visitas->countBy()->sortKeys()
            ->map(fn ($asistentes, $stands) => ['stands' => (int) $stands, 'asistentes' => $asistentes])
            ->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'minStands'         => $minStands,
                'empresasTotal'     => EmpresaExpositora::where('evento_id', $event->id)->count(),
                'asistentesConLeads' => $visitas->count(),
                'elegibles'         => $visitas->filter(fn ($stands) => $stands >= $minStands)->count(),
                'distribucion'      => $distribucion,
                'sorteos'           => Sorteo::where('evento_id', $event->id)
                    ->where('tipo', Sorteo::TIPO_PASAPORTE)
                    ->orderByDesc('sorteado_at')->orderByDesc('id')->limit(100)->get()
                    ->map(fn (Sorteo $s) => RealizarSorteoAction::presentar($s))->values(),
            ],
        ]);
    }

    public function sortear(Request $request, Evento $event, RealizarSorteoAction $sorteo): JsonResponse
    {
        $this->assertCanWriteEvento((int) $event->id);

        $data = $request->validate([
            'premio'     => ['required', 'string', 'max:150'],
            'min_stands' => ['nullable', 'integer', 'between:1,50'],
        ]);
        $minStands = $data['min_stands'] ?? $this->minStands($event);

        $elegibles = $this->standsPorAsistente($event)
            ->filter(fn ($stands) => $stands >= $minStands)
            ->keys();

        $yaGanaron = Sorteo::where('evento_id', $event->id)
            ->where('tipo', Sorteo::TIPO_PASAPORTE)
            ->pluck('participante_ganador_id');

        $resultado = $sorteo->ejecutar(
            $event,
            Sorteo::TIPO_PASAPORTE,
            $elegibles->diff($yaGanaron),
            trim($data['premio']),
            ['min_stands' => $minStands, 'excluir_ganadores' => true],
            null,
            auth('admins')->id(),
        );

        if ($resultado === null) {
            return response()->json([
                'success' => false,
                'error'   => "No hay asistentes disponibles: nadie (que no haya ganado ya) visitó al menos {$minStands} stands distintos.",
            ], 422);
        }

        AdminAuditLogger::log('sorteo_pasaporte', 'sorteo', $resultado->id, (int) $event->id, null, [
            'premio'           => $resultado->premio,
            'min_stands'       => $minStands,
            'candidatos_count' => $resultado->candidatos_count,
            'candidatos_hash'  => $resultado->candidatos_hash,
            'ganador_id'       => $resultado->participante_ganador_id,
        ]);

        return response()->json(['success' => true, 'sorteo' => RealizarSorteoAction::presentar($resultado)], 201);
    }

    /** N vigente: la configuración del evento, con el default y acotado a 1-50. */
    private function minStands(Evento $event): int
    {
        $n = (int) (($event->expositores_config ?? [])['pasaporte_min_stands'] ?? self::MIN_STANDS_DEFAULT);

        return min(50, max(1, $n));
    }

    /**
     * participante_id => cantidad de empresas expositoras DISTINTAS del evento
     * que lo capturaron (dos leads del mismo asistente en la misma empresa no
     * existen: UNIQUE por empresa+participante).
     *
     * @return Collection<int, int>
     */
    private function standsPorAsistente(Evento $event): Collection
    {
        return LeadCapturado::query()
            ->join('empresas_expositoras', 'empresas_expositoras.id', '=', 'leads_capturados.empresa_expositora_id')
            ->where('empresas_expositoras.evento_id', $event->id)
            ->selectRaw('leads_capturados.participante_id as participante, COUNT(DISTINCT leads_capturados.empresa_expositora_id) as stands')
            ->groupBy('leads_capturados.participante_id')
            ->get()
            ->mapWithKeys(fn ($fila) => [(int) $fila->participante => (int) $fila->stands]);
    }
}
