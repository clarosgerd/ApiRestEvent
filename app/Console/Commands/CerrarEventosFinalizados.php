<?php

namespace App\Console\Commands;

use App\Models\Evento;
use Illuminate\Console\Command;

class CerrarEventosFinalizados extends Command
{
    protected $signature = 'eventos:cerrar-finalizados';

    protected $description = 'Marca como closed los eventos cuya fecha_fin ya pasó.';

    public function handle(): int
    {
        $cerrados = Evento::whereNotNull('fecha_fin')
            ->where('fecha_fin', '<', now())
            ->where('estado_evento_id', '!=', 'closed')
            ->update(['estado_evento_id' => 'closed']);

        $this->info("Eventos cerrados: {$cerrados}");

        return self::SUCCESS;
    }
}
