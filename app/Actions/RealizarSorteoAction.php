<?php

namespace App\Actions;

use App\Models\EmpresaExpositora;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Sorteo;
use Illuminate\Support\Collection;

/**
 * SmartStand fase 4 — lógica única de sorteo, compartida por el sorteo de una
 * empresa (entre sus contactos) y el del pasaporte médico (organizador).
 *
 * La elección usa random_int() (CSPRNG del sistema), nunca rand/shuffle/
 * inRandomOrder: es un sorteo con premio y tiene que ser defendible. Los ids
 * candidatos se ordenan antes de elegir, así `candidatos_hash` (sha256 de la
 * lista ordenada) permite demostrar sobre qué conjunto exacto se sorteó.
 */
class RealizarSorteoAction
{
    /**
     * @param  Collection<int, int|string>  $participanteIds
     * @return array{ganador_id: int, candidatos_count: int, candidatos_hash: string}|null  null si no hay candidatos
     */
    public function elegir(Collection $participanteIds): ?array
    {
        $ids = $participanteIds->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $total = $ids->count();

        if ($total === 0) {
            return null;
        }

        return [
            'ganador_id'       => $ids[random_int(0, $total - 1)],
            'candidatos_count' => $total,
            'candidatos_hash'  => hash('sha256', $ids->implode(',')),
        ];
    }

    /**
     * Sortea y deja el registro. Null si no hay candidatos.
     *
     * @param  Collection<int, int|string>  $participanteIds
     * @param  array<string, mixed>          $filtros  lo que se aplicó para armar la lista (queda en el historial)
     */
    public function ejecutar(
        Evento $evento,
        string $tipo,
        Collection $participanteIds,
        string $premio,
        array $filtros = [],
        ?EmpresaExpositora $empresa = null,
        ?int $adminUserId = null,
    ): ?Sorteo {
        $resultado = $this->elegir($participanteIds);
        if ($resultado === null) {
            return null;
        }

        $ganador = Participante::findOrFail($resultado['ganador_id']);

        return Sorteo::create([
            'evento_id'               => $evento->id,
            'tipo'                    => $tipo,
            'empresa_expositora_id'   => $empresa?->id,
            'premio'                  => $premio,
            'participante_ganador_id' => $ganador->id,
            'ganador'                 => [
                'nombre'   => $ganador->nombre,
                'apellido' => $ganador->apellido,
                'correo'   => $ganador->correo,
                'telefono' => $ganador->telefono,
                'ciudad'   => $ganador->ciudad,
            ],
            'candidatos_count'        => $resultado['candidatos_count'],
            'candidatos_hash'         => $resultado['candidatos_hash'],
            'filtros'                 => $filtros ?: null,
            'admin_user_id'           => $adminUserId,
            'sorteado_at'             => now(),
        ]);
    }

    /** Forma común de un sorteo en las respuestas de la API. */
    public static function presentar(Sorteo $sorteo): array
    {
        return [
            'id'              => $sorteo->id,
            'tipo'            => $sorteo->tipo,
            'premio'          => $sorteo->premio,
            'sorteadoAt'      => optional($sorteo->sorteado_at)->toIso8601String(),
            'candidatosCount' => $sorteo->candidatos_count,
            'filtros'         => $sorteo->filtros ?? [],
            'ganador'         => $sorteo->ganador ?? [],
        ];
    }
}
