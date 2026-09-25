<?php

namespace App\Console\Commands;

use App\Actions\EnviarSeguimientoLeadAction;
use App\Models\LeadCapturado;
use Illuminate\Console\Command;

/**
 * SmartStand fase 4 — reintenta los correos de seguimiento que fallaron (SMTP
 * caído en el momento de la captura). Solo leads `fallido` de las últimas 48 h
 * y con menos de 3 intentos: un correo de seguimiento que llega días tarde ya no
 * sirve, y no se insiste sobre una dirección que rebota.
 */
class ReintentarSeguimientosExpositor extends Command
{
    protected $signature = 'expositores:reintentar-seguimientos
        {--horas=48 : Solo fallos de las últimas N horas}
        {--dry-run : Solo lista, no envía nada}';

    protected $description = 'Reintenta los correos de seguimiento de expositores que fallaron por error de envío.';

    public function handle(EnviarSeguimientoLeadAction $accion): int
    {
        $query = LeadCapturado::where('seguimiento_estado', EnviarSeguimientoLeadAction::ESTADO_FALLIDO)
            ->where('seguimiento_intentos', '<', EnviarSeguimientoLeadAction::MAX_INTENTOS)
            ->where('seguimiento_at', '>=', now()->subHours((int) $this->option('horas')));

        if ($this->option('dry-run')) {
            $this->info("[dry-run] {$query->count()} seguimiento(s) fallido(s) por reintentar.");

            return self::SUCCESS;
        }

        $enviados = 0;
        $pendientes = 0;
        $query->with(['empresa.evento', 'participante'])->chunkById(50, function ($leads) use ($accion, &$enviados, &$pendientes) {
            foreach ($leads as $lead) {
                $accion->ejecutar($lead)->seguimiento_estado === EnviarSeguimientoLeadAction::ESTADO_ENVIADO
                    ? $enviados++
                    : $pendientes++;
            }
        });

        $this->info("Seguimientos reintentados: {$enviados} enviados, {$pendientes} sin enviar.");

        return self::SUCCESS;
    }
}
