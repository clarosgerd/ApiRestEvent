<?php

namespace App\Console\Commands;

use App\Actions\ExpirarPagosAdicionalesAction;
use Illuminate\Console\Command;

class ExpirarPagosAdicionales extends Command
{
    protected $signature = 'notificaciones:expirar-pagos-adicionales';

    protected $description = 'Marca como expirados los pagos adicionales (SIP, agregar taller a inscripción pagada) que quedaron pending sin confirmarse.';

    public function handle(ExpirarPagosAdicionalesAction $action): int
    {
        $expirados = $action->handle();

        $this->info("Pagos adicionales expirados: {$expirados}");

        return self::SUCCESS;
    }
}
