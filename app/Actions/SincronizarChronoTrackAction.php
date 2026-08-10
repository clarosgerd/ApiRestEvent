<?php

namespace App\Actions;

use App\Models\Evento;
use App\Services\ChronoTrackClient;
use App\Support\ResultadosBulkImporter;

/**
 * Trae los resultados ya generados en ChronoTrack para un evento (que ya
 * tiene `chronotrack_event_id` seteado) y los guarda con la misma lógica que
 * ya usa la carga manual (`ResultadoController::bulk()` /
 * `ImportarResultadosAction`) — ver brain/groovy-chasing-ladybug.md.
 *
 * Además de finishers, detecta DNS/DNF cruzando `/entry` (quién se
 * inscribió) contra los intervals de cada carrera: un bib que nunca
 * apareció en el interval completo pero sí en un checkpoint parcial es
 * `dnf`; uno que no apareció en ningún timing point es `dns`. Si la
 * carrera no tiene ningún checkpoint parcial configurado en ChronoTrack,
 * no hay forma de distinguir uno del otro y todo lo no-finisher se marca
 * `dns` (más conservador).
 */
class SincronizarChronoTrackAction
{
    public function __construct(private ChronoTrackClient $client)
    {
    }

    /**
     * @return array{procesados: int, no_vinculados: array, intervals: int, dns: int, dnf: int}
     */
    public function handle(Evento $evento): array
    {
        if (!$evento->chronotrack_event_id) {
            throw new \InvalidArgumentException(
                "El evento {$evento->id} no tiene chronotrack_event_id configurado."
            );
        }

        $ctEventId = $evento->chronotrack_event_id;
        $intervalsCompletos = $this->client->intervalsCompletos($ctEventId);

        $parcialesPorCarrera = [];
        foreach ($this->client->intervalsParciales($ctEventId) as $parcial) {
            $parcialesPorCarrera[$parcial['race_id']][] = $parcial;
        }

        $items = [];
        $dns = 0;
        $dnf = 0;

        foreach ($intervalsCompletos as $interval) {
            $bibsFinishers = [];
            foreach ($this->client->resultadosDeInterval($interval['interval_id']) as $resultado) {
                $items[] = $this->transformarFinisher($resultado);
                if (!empty($resultado['results_bib'])) {
                    $bibsFinishers[$resultado['results_bib']] = true;
                }
            }

            $bibsEnParciales = [];
            foreach ($parcialesPorCarrera[$interval['race_id']] ?? [] as $parcial) {
                foreach ($this->client->resultadosDeInterval($parcial['interval_id']) as $r) {
                    if (!empty($r['results_bib'])) {
                        $bibsEnParciales[$r['results_bib']] = true;
                    }
                }
            }

            foreach ($this->client->entriesDeCarrera($interval['race_id']) as $entry) {
                $bib = $entry['entry_bib'] ?? null;
                // Sin bib asignado no hay nada que vincular; ya finisher no
                // es DNS/DNF.
                if (empty($bib) || isset($bibsFinishers[$bib])) {
                    continue;
                }

                $estado = isset($bibsEnParciales[$bib]) ? 'dnf' : 'dns';
                $estado === 'dnf' ? $dnf++ : $dns++;

                $items[] = [
                    'chip'               => null,
                    'numero_corredor'    => $bib,
                    'numero_documento'   => null,
                    'tiempo_oficial'     => null,
                    'tiempo_chip'        => null,
                    'posicion_general'   => null,
                    'posicion_categoria' => null,
                    'posicion_genero'    => null,
                    'estado'             => $estado,
                ];
            }
        }

        $importado = app(ImportarResultadosAction::class)->handle($evento, $items);

        return $importado + [
            'intervals' => count($intervalsCompletos),
            'dns'       => $dns,
            'dnf'       => $dnf,
        ];
    }

    /**
     * Mapeo de campos — ver tabla en brain/groovy-chasing-ladybug.md.
     * `numero_documento` no viene de ChronoTrack; el matching de
     * `ImportarResultadosAction` ya prioriza `numero_corredor` (el bib)
     * antes que el documento, así que alcanza con el bib para vincular.
     */
    private function transformarFinisher(array $resultado): array
    {
        // Tiempo chip solo tiene sentido si hubo mat de arranque por chip
        // (results_begin_chip_time); si no, el chip time es el mismo que el
        // de gun y no aporta nada distinto.
        $tiempoChip = !empty($resultado['results_begin_chip_time'])
            ? ($resultado['results_time_with_penalty'] ?? null)
            : null;

        return [
            'chip'               => null,
            'numero_corredor'    => $resultado['results_bib'] ?? null,
            'numero_documento'   => null,
            'tiempo_oficial'     => $resultado['results_gun_time_with_penalty'] ?? $resultado['results_gun_time'] ?? null,
            'tiempo_chip'        => $tiempoChip,
            // Solo válido si el interval consultado está scopeado al
            // bracket "Overall" (ver intervalsCompletos) — si en el futuro
            // se consultan brackets por categoría, esto necesita ajustarse.
            'posicion_general'   => isset($resultado['results_rank']) ? (int) $resultado['results_rank'] : null,
            'posicion_categoria' => null,
            'posicion_genero'    => null,
            'estado'             => 'finisher',
        ];
    }
}
