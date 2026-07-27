<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Services\NotificacionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecordatorioKit extends Command
{
    protected $signature = 'notificaciones:recordatorio-kit';

    protected $description = 'Envía a los registros pagados el recordatorio de recoger KIT (numeración, poleras, souvenirs) según los días configurados por organizador.';

    public function handle(NotificacionService $notificaciones): int
    {
        $procesados = 0;

        Registration::where('pago_status', 'paid')
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

                    // Evento ya pasó — no tiene sentido recordar el KIT.
                    if ($diasHastaEvento < 0) {
                        continue;
                    }

                    $diasRecordatorioKit = $evento->organizador?->dias_recordatorio_kit ?? 5;

                    if ($diasHastaEvento <= $diasRecordatorioKit) {
                        $notificaciones->notificarRecordatorioKit($registration);
                        $procesados++;
                    }
                }
            });

        $this->info("Recordatorios de KIT procesados: {$procesados}");

        return self::SUCCESS;
    }
}
