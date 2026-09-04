<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Services\NotificacionService;
use Illuminate\Console\Command;

/**
 * Diagnóstico 04/09/2026 (reportado por el usuario, evento "Naranjillo Ultra
 * Trail", pago vía Multipago): varias inscripciones quedaron `pago_status =
 * 'paid'` sin correo de "pago confirmado" (`registration_notifications` sin
 * fila `pago_confirmado`/`email`).
 *
 * Causa raíz: `RegistrationService::updatePaymentStatus()` es el ÚNICO
 * lugar del código que dispara `NotificacionService::notificarPagoConfirmado()`.
 * Confirmado con el usuario: para este organizador/proveedor, `pago_status`
 * se actualiza con un UPDATE SQL directo contra la BD, sin pasar por
 * `PATCH /registrations/{referencia}/payment` (ni por ningún otro endpoint
 * de este código) — un UPDATE SQL directo no ejecuta PHP, así que ningún
 * código de esta app puede "enterarse" en el momento, sin importar qué tan
 * bien esté armado `updatePaymentStatus()`. No es un bug de ese método: es
 * un flujo externo que nunca pasa por acá.
 *
 * Mientras no se cambie ESE flujo externo (fuera del alcance de este
 * comando), la única forma de que estos correos salgan es una reconciliación
 * periódica: buscar inscripciones `paid` sin la notificación y mandarla.
 * `notificarPagoConfirmado()` ya es idempotente (ver
 * NotificacionService::reservarNotificacion()) — correr esto de más no
 * duplica nada, solo procesa lo que realmente falta.
 *
 * `origen_legado` excluido a propósito — inscripciones importadas por el
 * ETL de datos históricos (ver brain/PLAN-ETL-DATOS-HISTORICOS...) nunca
 * tuvieron ninguna notificación real y no corresponde mandarles un correo
 * de "pago confirmado" ahora, años después.
 *
 * `--dias` acota la ventana de inscripciones a revisar (default 90) — ver
 * feedback_dry_run_antes_de_correr_contra_bd_real: antes de agendarlo
 * recurrente, correr primero con `--dry-run` para dimensionar el impacto
 * real contra la BD de producción.
 */
class ReenviarPagoConfirmadoFaltante extends Command
{
    protected $signature = 'notificaciones:pago-confirmado-faltante
        {--dias=90 : Solo inscripciones creadas en los últimos N días}
        {--dry-run : Solo cuenta/lista, no envía nada}';

    protected $description = 'Reenvía el correo de "pago confirmado" a inscripciones pagadas que quedaron sin esa notificación (ej. pago_status actualizado por SQL directo, sin pasar por la app).';

    public function handle(NotificacionService $notificaciones): int
    {
        $dias = (int) $this->option('dias');
        $dryRun = (bool) $this->option('dry-run');

        $query = Registration::where('pago_status', 'paid')
            ->whereNull('origen_legado')
            ->where('created_at', '>=', now()->subDays($dias))
            ->whereDoesntHave('registrationNotifications', function ($q) {
                $q->where('tipo', 'pago_confirmado')->where('canal', 'email');
            });

        if ($dryRun) {
            $faltantes = $query->pluck('referencia');
            $this->info("[dry-run] {$faltantes->count()} inscripción(es) pagada(s) sin correo de confirmación (últimos {$dias} días):");
            foreach ($faltantes as $referencia) {
                $this->line(" - {$referencia}");
            }

            return self::SUCCESS;
        }

        $procesados = 0;
        $query->chunkById(100, function ($registrations) use ($notificaciones, &$procesados) {
            foreach ($registrations as $registration) {
                $notificaciones->notificarPagoConfirmado($registration);
                $procesados++;
            }
        });

        $this->info("Correos de pago confirmado reenviados: {$procesados}");

        return self::SUCCESS;
    }
}
