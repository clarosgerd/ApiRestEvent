<?php

namespace App\Console\Commands;

use App\Actions\PromoverListaEsperaAction;
use Illuminate\Console\Command;

/**
 * Promoción automática de lista de espera — ver
 * PromoverListaEsperaAction y PRD-kit-tallas-stock-lista-espera.md.
 * Corre diario (routes/console.php) — la persona ya está esperando hace
 * rato si llegó a la lista, no hace falta tiempo real.
 *
 * Idempotente: solo procesa filas en estado `pendiente`; una vez
 * promovida (o si el correo falla y queda pendiente para reintentar) no
 * se reenvía dos veces el mismo aviso a quien ya lo recibió.
 */
class ListaEsperaPromover extends Command
{
    protected $signature = 'lista-espera:promover';

    protected $description = 'Notifica a quienes corresponda de la lista de espera cuando se libera cupo o stock.';

    public function handle(PromoverListaEsperaAction $action): int
    {
        $promovidos = $action->handle();

        $this->info("Promociones de lista de espera notificadas: {$promovidos}.");

        return self::SUCCESS;
    }
}
