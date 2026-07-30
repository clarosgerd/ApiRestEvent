<?php

namespace App\Console\Commands;

use App\Models\Evento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class GenerarLinkDashboardOrganizador extends Command
{
    protected $signature = 'organizador:generar-link {evento : ID del evento}';

    protected $description = 'Genera el link firmado (sin expiración) del dashboard privado del organizador para un evento.';

    public function handle(): int
    {
        $evento = Evento::find($this->argument('evento'));

        if (!$evento) {
            $this->error('No existe un evento con ese ID.');

            return self::FAILURE;
        }

        $url = URL::signedRoute('organizador.dashboard', ['evento' => $evento->id]);

        $this->info("Dashboard de \"{$evento->nombre}\":");
        $this->line($url);

        return self::SUCCESS;
    }
}
