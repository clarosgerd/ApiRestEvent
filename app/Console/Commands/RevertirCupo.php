<?php

namespace App\Console\Commands;

use App\Models\RegistrationNotification;
use App\Services\RegistrationService;
use Illuminate\Console\Command;

class RevertirCupo extends Command
{
    protected $signature = 'notificaciones:revertir-cupo';

    protected $description = 'Cancela inscripciones pendientes cuyos días de gracia (tras el 2do aviso de 15 días) ya vencieron.';

    public function handle(RegistrationService $registrationService): int
    {
        $revertidos = 0;

        RegistrationNotification::where('tipo', 'recordatorio_15')
            ->where('canal', 'email')
            ->with('registration.evento.organizador')
            ->chunkById(100, function ($notificaciones) use ($registrationService, &$revertidos) {
                foreach ($notificaciones as $notificacion) {
                    $registration = $notificacion->registration;

                    // Ya pagó, ya se revirtió, o falló por otra razón — nada que hacer.
                    if (!$registration || $registration->pago_status !== 'pending') {
                        continue;
                    }

                    $diasGracia = $registration->evento?->organizador?->dias_gracia_reversion ?? 3;
                    $vence      = $notificacion->enviado_at->copy()->addDays($diasGracia);

                    if (now()->greaterThanOrEqualTo($vence)) {
                        $registrationService->updatePaymentStatus($registration->referencia, 'cancelled');
                        $revertidos++;
                    }
                }
            });

        $this->info("Registros revertidos: {$revertidos}");

        return self::SUCCESS;
    }
}
