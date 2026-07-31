<?php

namespace App\Console\Commands;

use App\Models\Evento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class GenerarLinkDelivery extends Command
{
    protected $signature = 'delivery:generar-link {evento : ID del evento}';

    protected $description = 'Genera el link firmado (sin expiración) del dashboard de delivery de kits para un evento.';

    public function handle(): int
    {
        $evento = Evento::find($this->argument('evento'));

        if (!$evento) {
            $this->error('No existe un evento con ese ID.');

            return self::FAILURE;
        }

        $this->info("Delivery de \"{$evento->nombre}\":");
        $this->line('Dashboard (HTML, para el organizador): ' . URL::signedRoute('delivery.dashboard', ['evento' => $evento->id]));
        $this->line('CSV (descarga puntual): ' . URL::signedRoute('delivery.dashboard.export', ['evento' => $evento->id]));
        $this->line('JSON (link de consumo, para integrar con el sistema de la empresa de delivery): ' . URL::signedRoute('delivery.dashboard.json', ['evento' => $evento->id]));

        return self::SUCCESS;
    }
}
