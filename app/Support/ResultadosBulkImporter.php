<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\Participante;
use App\Models\Resultado;

/**
 * Lógica de "guardar resultados en bulk" extraída de
 * `ResultadoController::bulk()` para poder reusarla tal cual desde otros
 * orígenes de datos (ver `ChronoTrackSyncService`) sin duplicar el
 * matching/upsert — una sola fuente de verdad para ambos caminos.
 *
 * Matchea participantes por chip → número de corredor → número de
 * documento (en ese orden de prioridad) dentro del evento — ver
 * brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §2.
 */
class ResultadosBulkImporter
{
    /**
     * @param Evento $evento
     * @param array<int, array{chip?: ?string, numero_corredor?: ?string, numero_documento?: ?string, tiempo_oficial?: ?string, tiempo_chip?: ?string, posicion_general?: ?int, posicion_categoria?: ?int, posicion_genero?: ?int, estado?: ?string}> $items
     * @return array{procesados: int, no_vinculados: array}
     */
    public static function importar(Evento $evento, array $items): array
    {
        $procesados = 0;
        $noVinculados = [];

        foreach ($items as $item) {
            $participante = self::resolverParticipante($evento, $item);

            $llave = $participante
                ? ['event_id' => $evento->id, 'participante_id' => $participante->id]
                : [
                    'event_id'         => $evento->id,
                    'participante_id'  => null,
                    'chip'             => $item['chip'] ?? null,
                    'numero_corredor'  => $item['numero_corredor'] ?? null,
                    'numero_documento' => $item['numero_documento'] ?? null,
                ];

            Resultado::updateOrCreate($llave, [
                'chip'               => $item['chip'] ?? null,
                'numero_corredor'    => $item['numero_corredor'] ?? null,
                'numero_documento'   => $item['numero_documento'] ?? null,
                'tiempo_oficial'     => $item['tiempo_oficial'] ?? null,
                'tiempo_chip'        => $item['tiempo_chip'] ?? null,
                'posicion_general'   => $item['posicion_general'] ?? null,
                'posicion_categoria' => $item['posicion_categoria'] ?? null,
                'posicion_genero'    => $item['posicion_genero'] ?? null,
                'estado'             => $item['estado'] ?? 'finisher',
            ]);

            $procesados++;
            if (!$participante) {
                $noVinculados[] = $item;
            }
        }

        return ['procesados' => $procesados, 'no_vinculados' => $noVinculados];
    }

    private static function resolverParticipante(Evento $evento, array $item): ?Participante
    {
        $base = fn () => Participante::whereHas('registration', function ($q) use ($evento) {
            $q->where('evento_id', $evento->id);
        });

        if (!empty($item['chip'])) {
            $p = $base()->where('chip', $item['chip'])->first();
            if ($p) return $p;
        }

        if (!empty($item['numero_corredor'])) {
            $p = $base()->where('numero_corredor', $item['numero_corredor'])->first();
            if ($p) return $p;
        }

        if (!empty($item['numero_documento'])) {
            $p = $base()->where('numero_documento', $item['numero_documento'])->first();
            if ($p) return $p;
        }

        return null;
    }
}
