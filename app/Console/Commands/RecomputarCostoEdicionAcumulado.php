<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Registration;
use Illuminate\Console\Command;

/**
 * Backfill/recompute de `registration_totals.costo_edicion_acumulado`
 * (04/09/2026) — pedido del usuario: "que pasa con los cobros que ya se
 * hicieron... que el reporte sea fidedigno". La columna se agregó junto
 * con el fix de "Ingreso por ediciones" en el dashboard (ver
 * App\Support\BalanceEventoData) y arranca en 0 para toda inscripción ya
 * editada ANTES de ese deploy, aunque el cobro real haya existido.
 *
 * `AuditLog` tiene una fila por cada edición pagada real que ocurrió
 * (Caja, SIP o autoservicio — las 3 pasan por
 * `ActualizarInscripcionPagadaAction::handle()`, que crea esta fila
 * incondicionalmente al final), desde siempre, con fecha. Contar esas
 * filas por inscripción y multiplicar por el `costo_edicion` ACTUAL del
 * tipo de formulario reconstruye el total con una sola aproximación
 * conocida: si `costo_edicion` cambió de valor en el medio, el número
 * reconstruido para ediciones viejas usa el valor de HOY, no el que regía
 * en ese momento — decisión explícita del usuario, prefiere esto a dejar
 * el histórico en 0.
 *
 * Idempotente y seguro de correr más de una vez: siempre RECALCULA desde
 * `AuditLog` (la fuente de verdad real de "cuántas veces se cobró"), no
 * incrementa sobre lo que ya había — así que correrlo de nuevo no duplica
 * nada, sea la primera vez o la enésima.
 */
class RecomputarCostoEdicionAcumulado extends Command
{
    protected $signature = 'ingresos:recomputar-costo-edicion {--dry-run : Solo muestra qué cambiaría, no escribe nada}';

    protected $description = 'Reconstruye registration_totals.costo_edicion_acumulado a partir del historial real de ediciones pagadas (AuditLog), para inscripciones editadas antes de que existiera esta columna.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tocados = 0;

        Registration::with(['formType', 'totals'])
            ->whereHas('auditLogs')
            ->chunkById(100, function ($registrations) use ($dryRun, &$tocados) {
                foreach ($registrations as $registration) {
                    $ediciones = AuditLog::where('registration_id', $registration->id)->count();
                    $fee = (float) ($registration->formType->costo_edicion ?? 0);
                    $nuevo = round($ediciones * $fee, 2);
                    $actual = (float) ($registration->totals->costo_edicion_acumulado ?? 0);

                    if (abs($nuevo - $actual) < 0.01) {
                        continue; // ya está correcto (incluye el caso fee=0, sin nada que reconstruir)
                    }

                    if ($dryRun) {
                        $this->line("{$registration->referencia}: {$actual} -> {$nuevo} ({$ediciones} edición(es) x {$fee})");
                    } elseif ($registration->totals) {
                        $registration->totals->update(['costo_edicion_acumulado' => $nuevo]);
                    }

                    $tocados++;
                }
            });

        $prefix = $dryRun ? '[dry-run] ' : '';
        $verbo = $dryRun ? 'a actualizar' : 'actualizados';
        $this->info("{$prefix}Registros {$verbo}: {$tocados}");

        return self::SUCCESS;
    }
}
