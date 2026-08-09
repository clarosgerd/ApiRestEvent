<?php

namespace App\Console\Commands;

use App\Models\Evento;
use App\Services\ChronoTrackSyncService;
use Illuminate\Console\Command;

/**
 * Trae resultados desde ChronoTrack para un evento que ya tiene
 * `chronotrack_event_id` seteado y los guarda en `resultados` — mismo
 * camino que la carga manual (`ResultadoController::bulk()`). Ver
 * brain/groovy-chasing-ladybug.md.
 *
 * MVP: se corre a mano cuando el organizador avisa que ChronoTrack ya tiene
 * los resultados listos (result_generation_status: COMPLETED del lado de
 * ellos) — sin programar ni botón en el panel todavía.
 */
class ChronoTrackSincronizar extends Command
{
    protected $signature = 'chronotrack:sincronizar {evento : ID del evento (nuestro, no el de ChronoTrack)}';

    protected $description = 'Trae los resultados (finishers) desde ChronoTrack para un evento y los guarda en resultados.';

    public function handle(ChronoTrackSyncService $service): int
    {
        $evento = Evento::find($this->argument('evento'));

        if (!$evento) {
            $this->error('No existe un evento con ese ID.');

            return self::FAILURE;
        }

        if (!$evento->chronotrack_event_id) {
            $this->error("El evento \"{$evento->nombre}\" (id {$evento->id}) no tiene chronotrack_event_id configurado.");

            return self::FAILURE;
        }

        $this->info("Sincronizando \"{$evento->nombre}\" desde ChronoTrack (event_id {$evento->chronotrack_event_id})...");

        try {
            $resultado = $service->sincronizar($evento);
        } catch (\Throwable $e) {
            $this->error('Falló la sincronización: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Intervals procesados: {$resultado['intervals']}");
        $this->info("Resultados guardados: {$resultado['procesados']} (dns: {$resultado['dns']}, dnf: {$resultado['dnf']})");

        if (!empty($resultado['no_vinculados'])) {
            $this->warn(count($resultado['no_vinculados']) . ' resultado(s) sin participante vinculado (bib no matchea ninguna inscripción):');
            foreach ($resultado['no_vinculados'] as $item) {
                $this->line('  - bib ' . ($item['numero_corredor'] ?? '?'));
            }
        }

        return self::SUCCESS;
    }
}
