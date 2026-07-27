<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Services\NotificacionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecordatorioPendientes extends Command
{
    protected $signature = 'notificaciones:recordatorio-pendientes';

    protected $description = 'Envía el 1er (30d) y 2do (15d) aviso de pago pendiente según los días configurados por organizador.';

    public function handle(NotificacionService $notificaciones): int
    {
        $procesados = 0;

        Registration::where('pago_status', 'pending')
            ->with('evento.organizador')
            ->chunkById(100, function ($registrations) use ($notificaciones, &$procesados) {
                foreach ($registrations as $registration) {
                    $evento = $registration->evento;
                    if (!$evento || !$evento->fecha_inicio) {
                        continue;
                    }

                    $diasHastaEvento = now()->startOfDay()->diffInDays(
                        Carbon::parse($evento->fecha_inicio)->startOfDay(),
                        false
                    );

                    // Evento ya pasó — no tiene sentido recordar el pago.
                    if ($diasHastaEvento < 0) {
                        continue;
                    }

                    $organizador = $evento->organizador;
                    $dias1       = $organizador->dias_recordatorio_pendiente_1 ?? 30;
                    $dias2       = $organizador->dias_recordatorio_pendiente_2 ?? 15;
                    $diasGracia  = $organizador->dias_gracia_reversion ?? 3;

                    // El de 15 días (tier 2) tiene prioridad si ambos umbrales ya
                    // se cumplen — es idempotente vía registration_notifications,
                    // así que si el tier 1 no se había mandado (cron caído, etc.)
                    // simplemente no se manda más, no hace falta "recuperarlo".
                    if ($diasHastaEvento <= $dias2) {
                        $notificaciones->notificarRecordatorioPendiente($registration, 2, $diasGracia);
                        $procesados++;
                    } elseif ($diasHastaEvento <= $dias1) {
                        $notificaciones->notificarRecordatorioPendiente($registration, 1, $diasGracia);
                        $procesados++;
                    }
                }
            });

        $this->info("Recordatorios procesados: {$procesados}");

        return self::SUCCESS;
    }
}
