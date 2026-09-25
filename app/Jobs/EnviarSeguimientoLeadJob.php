<?php

namespace App\Jobs;

use App\Actions\EnviarSeguimientoLeadAction;
use App\Models\LeadCapturado;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * SmartStand fase 4. Deliberadamente NO implementa ShouldQueue: se despacha con
 * `->afterResponse()`, o sea corre en el `terminate` de la misma petición, ya
 * respondido el celular del staff. La cola `database` solo corre cada 8 horas;
 * un correo de seguimiento encolado ahí llegaría horas tarde.
 */
class EnviarSeguimientoLeadJob
{
    use Dispatchable;

    public function __construct(public int $leadId)
    {
    }

    public function handle(EnviarSeguimientoLeadAction $accion): void
    {
        $lead = LeadCapturado::with(['empresa.evento', 'participante'])->find($this->leadId);
        if ($lead) {
            $accion->ejecutar($lead);
        }
    }
}
