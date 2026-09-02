<?php

namespace App\Console\Commands;

use App\Models\PagoAdicionalInscripcion;
use App\Services\NotificacionService;
use Illuminate\Console\Command;

/**
 * Reenvío puntual del correo de confirmación de un pago adicional
 * (02/09/2026) — ver NotificacionService::notificarPagoAdicionalConfirmado().
 * Pensado para 2 casos:
 * 1. Retroactivo: pagos adicionales confirmados ANTES de que este correo
 *    existiera (incidente real en UAT, 02/09/2026 — ver
 *    tests/Feature/PagoAdicionalSipTest.php) — nunca se les notificó nada.
 * 2. Reenvío manual si alguna vez el correo se pierde/no llega.
 *
 * Por default corre en modo reporte (no envía nada) — usar --confirmar
 * para enviar en serio. Pensado para correr una sola vez vía Cron Jobs de
 * cPanel (no hay SSH), no queda programado como tarea recurrente. Mismo
 * criterio que PurgarDatosPersonaCanceladaRetroactivo.
 */
class ReenviarPagoAdicionalConfirmado extends Command
{
    protected $signature = 'notificaciones:reenviar-pago-adicional
        {referencia : Referencia del pago adicional (AD-XXXXXXXX)}
        {--confirmar : Sin esto, solo muestra a quién se le enviaría (dry-run)}
        {--forzar : Reenvía aunque ya se haya notificado antes (por default no reenvía)}';

    protected $description = 'Reenvía (o reporta) el correo de confirmación de un pago adicional puntual';

    public function handle(NotificacionService $notificaciones): int
    {
        $referencia = trim((string) $this->argument('referencia'));
        $confirmar  = (bool) $this->option('confirmar');
        $forzar     = (bool) $this->option('forzar');

        $pago = PagoAdicionalInscripcion::where('referencia', $referencia)->first();
        if (!$pago) {
            $this->error("No existe ningún pago adicional con referencia '{$referencia}'.");

            return self::FAILURE;
        }

        if ($pago->pago_status !== 'paid') {
            $this->error("El pago adicional '{$referencia}' no está pagado (estado: {$pago->pago_status}) — no corresponde notificar nada.");

            return self::FAILURE;
        }

        $registration = $pago->registration;
        if (!$registration) {
            $this->error("El pago adicional '{$referencia}' no tiene inscripción asociada (dato corrupto).");

            return self::FAILURE;
        }

        if ($pago->notificado_at !== null && !$forzar) {
            $this->info("Ya se notificó este pago adicional el {$pago->notificado_at->format('d/m/Y H:i')} — usá --forzar si de verdad querés reenviarlo.");

            return self::SUCCESS;
        }

        $destinatarios = $registration->participants->pluck('correo')->filter()->unique();

        $this->info("Pago adicional: {$pago->referencia} (inscripción {$registration->referencia}, evento: {$registration->evento_nombre})");
        $this->info("Monto: {$pago->moneda_pago} " . number_format((float) $pago->monto, 2));
        $this->info('Se notificaría a: ' . ($destinatarios->isEmpty() ? '(sin correos)' : $destinatarios->implode(', ')));

        if (!$confirmar) {
            $this->warn('Dry-run — no se envió nada. Agregá --confirmar para enviar en serio.');

            return self::SUCCESS;
        }

        if ($forzar) {
            // notificarPagoAdicionalConfirmado() es idempotente por diseño
            // (chequea notificado_at) — para permitir un reenvío deliberado
            // hay que resetear la marca antes de llamarla.
            $pago->update(['notificado_at' => null]);
        }

        $notificaciones->notificarPagoAdicionalConfirmado($pago);

        $pago->refresh();
        if ($pago->notificado_at !== null) {
            $this->info('Correo enviado y marcado como notificado.');

            return self::SUCCESS;
        }

        $this->error('No se pudo enviar — revisar storage/logs/laravel.log.');

        return self::FAILURE;
    }
}
