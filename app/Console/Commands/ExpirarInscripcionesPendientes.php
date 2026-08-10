<?php

namespace App\Console\Commands;

use App\Actions\ExpirarInscripcionesPendientesAction;
use Illuminate\Console\Command;

class ExpirarInscripcionesPendientes extends Command
{
    protected $signature = 'notificaciones:expirar-pendientes';

    protected $description = 'Cancela inscripciones pendientes cuyo tiempo_expiracion_min del form_type ya venció, liberando el cupo.';

    public function handle(ExpirarInscripcionesPendientesAction $action): int
    {
        $expiradas = $action->handle();

        $this->info("Inscripciones expiradas: {$expiradas}");

        return self::SUCCESS;
    }
}
