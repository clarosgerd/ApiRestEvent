<?php

namespace App\Console\Commands;

use App\Actions\ExpirarPagosSaldoAction;
use Illuminate\Console\Command;

class ExpirarPagosSaldo extends Command
{
    protected $signature = 'notificaciones:expirar-pagos-saldo';

    protected $description = 'Marca como expirados los pagos online de saldo pendiente (reserva de cupo con seña) que quedaron pending sin confirmarse.';

    public function handle(ExpirarPagosSaldoAction $action): int
    {
        $expirados = $action->handle();

        $this->info("Pagos de saldo expirados: {$expirados}");

        return self::SUCCESS;
    }
}
