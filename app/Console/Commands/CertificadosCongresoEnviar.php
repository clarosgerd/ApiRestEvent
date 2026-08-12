<?php

namespace App\Console\Commands;

use App\Actions\EnviarCertificadosCongresoAction;
use App\Models\Evento;
use Illuminate\Console\Command;

/**
 * Certificados automáticos de congreso — ver
 * EnviarCertificadosCongresoAction y elascenso/event/brain/ (sesión
 * 11/08/2026). Corre diario (routes/console.php) — no hay urgencia de
 * minutos, el trigger es el cierre del evento, que ya se evalúa una vez
 * al día (`eventos:cerrar-finalizados`).
 *
 * Idempotente: se puede correr todos los días sin reenviar certificados
 * ya mandados (ver CertificadoCongresoEnviado) — solo se procesan los
 * eventos congreso ya cerrados, cualquier participante ya certificado
 * se salta.
 */
class CertificadosCongresoEnviar extends Command
{
    protected $signature = 'certificados:enviar-congreso';

    protected $description = 'Envía el certificado de asistencia automático a los participantes de eventos tipo Congreso ya cerrados.';

    public function handle(EnviarCertificadosCongresoAction $action): int
    {
        $eventos = Evento::where('estado_evento_id', 'closed')
            ->whereHas('tipoEvento', fn ($q) => $q->where('nombre', 'Congreso / No aplica'))
            ->get();

        $totalEnviados = 0;
        foreach ($eventos as $evento) {
            $totalEnviados += $action->handle($evento);
        }

        $this->info("Eventos congreso cerrados revisados: {$eventos->count()}. Certificados enviados: {$totalEnviados}.");

        return self::SUCCESS;
    }
}
