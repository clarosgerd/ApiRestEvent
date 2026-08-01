<?php

namespace App\Console\Commands;

use App\Actions\EnviarDashboardOrganizadorAction;
use App\Models\Evento;
use Illuminate\Console\Command;

class EnviarDashboardOrganizador extends Command
{
    protected $signature = 'notificaciones:enviar-dashboard-organizador {evento : ID del evento}';

    protected $description = 'Reenvía a demanda (sin esperar los 15 días) el correo con el link del dashboard de organizador y, si aplica, el de delivery.';

    public function handle(EnviarDashboardOrganizadorAction $action): int
    {
        $evento = Evento::with(['formTypes', 'organizador'])->find($this->argument('evento'));

        if (!$evento) {
            $this->error('No existe un evento con ese ID.');

            return self::FAILURE;
        }

        $action->handle($evento);

        $this->info("Dashboard reenviado para \"{$evento->nombre}\".");

        return self::SUCCESS;
    }
}
