<?php

namespace App\Console\Commands;

use App\Models\EventoSyncExternoConfig;
use App\Services\SyncExternoPullService;
use Illuminate\Console\Command;

/**
 * Sync periódico (pull) de participantes de un evento con registro propio
 * externo (17/09/2026) — recorre todas las fuentes activas
 * (`eventos_sync_externo`) y llama a `SyncExternoPullService` para cada una.
 * Nombrado en plural/genérico a propósito (no menciona ningún evento ni
 * fuente en particular) — sirve tal cual para la próxima fuente que
 * aparezca, sin código nuevo. Un error en una fuente no frena a las demás.
 *
 * Programado cada hora en routes/console.php.
 */
class SincronizarParticipantesExternos extends Command
{
    protected $signature = 'participantes-externos:sincronizar-fuentes {--evento= : Solo esta fuente (por evento_id), para probar antes de dejarlo en manos del cron}';

    protected $description = 'Sincroniza (pull) los participantes de cada fuente externa activa hacia Registration/Participante.';

    public function handle(SyncExternoPullService $service): int
    {
        $query = EventoSyncExternoConfig::where('activo', true);

        if ($eventoId = $this->option('evento')) {
            $query->where('evento_id', $eventoId);
        }

        $configs = $query->get();

        if ($configs->isEmpty()) {
            $this->info('No hay fuentes activas para sincronizar.');

            return self::SUCCESS;
        }

        $huboError = false;

        foreach ($configs as $config) {
            $this->info("Sincronizando evento #{$config->evento_id} ({$config->nombre_fuente})...");

            try {
                $resumen = $service->sincronizar($config);
            } catch (\Throwable $e) {
                $huboError = true;
                $this->error("  Falló sin llegar a procesar nada: {$e->getMessage()}");

                continue;
            }

            if ($resumen['error']) {
                $huboError = true;
                $this->error("  {$resumen['error']}");

                continue;
            }

            $this->info("  Creados: {$resumen['creados']} | Actualizados: {$resumen['actualizados']} | Omitidos: " . count($resumen['omitidos']));
        }

        return $huboError ? self::FAILURE : self::SUCCESS;
    }
}
