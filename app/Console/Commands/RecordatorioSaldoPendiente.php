<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Services\NotificacionService;
use Illuminate\Console\Command;

class RecordatorioSaldoPendiente extends Command
{
    protected $signature = 'notificaciones:recordatorio-saldo-pendiente';

    protected $description = 'Avisa a las inscripciones pagadas con saldo pendiente (reserva de cupo con seña) un día antes de la fecha límite de pago del saldo.';

    public function handle(NotificacionService $notificaciones): int
    {
        $procesados = 0;

        // pago_status sigue siendo 'paid' (reserva ya cobrada) — lo único
        // que falta es el saldo, nunca la inscripción entera. Ver
        // DEPLOY-CHECKLIST-RESERVA-CUPO-SENA-21102026.md.
        Registration::where('pago_status', 'paid')
            ->where('saldo_pendiente', '>', 0)
            ->with('evento')
            ->chunkById(100, function ($registrations) use ($notificaciones, &$procesados) {
                foreach ($registrations as $registration) {
                    $evento = $registration->evento;
                    $fechaLimite = $evento?->fechaLimiteSaldoReserva();
                    if (!$fechaLimite) {
                        continue;
                    }

                    $diasHastaFecha = now()->startOfDay()->diffInDays(
                        $fechaLimite->copy()->startOfDay(),
                        false
                    );

                    // "Un día antes" (pedido real del usuario) — se manda
                    // cuando falta 1 día o menos (incluye el mismo día y
                    // atrasos, para no perder a quien reservó demasiado
                    // cerca de la fecha límite como para recibir el aviso
                    // exactamente el día anterior). Idempotente vía
                    // registration_notifications — nunca se reenvía, así
                    // que una corrida diaria de este comando solo dispara
                    // el correo una vez por inscripción.
                    if ($diasHastaFecha <= 1) {
                        $notificaciones->notificarRecordatorioSaldoPendiente($registration);
                        $procesados++;
                    }
                }
            });

        $this->info("Recordatorios de saldo pendiente procesados: {$procesados}");

        return self::SUCCESS;
    }
}
