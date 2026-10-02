<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\CajaMovimiento;
use App\Models\Registration;
use App\Support\SnapshotInscripcionPagadaData;
use Illuminate\Console\Command;

/**
 * Reparación del bug real de snapshot corrompido en inscripciones pagadas
 * editadas (30/09/2026, ver SnapshotInscripcionPagadaData) — barrido único,
 * NO se agenda como los otros comandos de reconciliación de este proyecto:
 * de acá en más el fix en caliente (ActualizarInscripcionPagadaAction) ya
 * mantiene todo consistente en cada edición nueva; esto es solo para reparar
 * lo que quedó corrompido ANTES del fix.
 *
 * `audit_logs` (no `caja_movimientos`) es la fuente de "esta inscripción fue
 * editada al menos una vez" — se crea una fila ahí en CADA llamada a
 * ActualizarInscripcionPagadaAction::handle(), tanto Caja como autoservicio;
 * `caja_movimientos` solo existe para el camino de Caja y solo cuando el
 * delta es distinto de 0.
 *
 * `precio_categoria`/`subtotal` SOLO se reconstruyen contra el catálogo
 * (`SnapshotInscripcionPagadaData::recalcular($r, true)`) cuando hay
 * evidencia real de que esta inscripción pasó por el camino con el bug —
 * un `caja_movimientos` tipo=edicion_pagada (creado únicamente por
 * CajaController::editarPagada(), el único lugar que manda una categoría en
 * cualquier dirección con desembolso real en efectivo). Sin esa evidencia,
 * "vigente hoy" puede no ser el precio que era real el día de una edición
 * pasada (categoría con precio por período ya vencido, o precio base que
 * subió después sin dejar historial) — dos falsos positivos reales
 * encontrados en UAT el 02/10/2026, ninguno pasó nunca por Caja.
 */
class ReconciliarSnapshotInscripcionesPagadas extends Command
{
    protected $signature = 'caja:reconciliar-snapshot-inscripciones-pagadas
        {--dry-run : Solo lista las diferencias, no aplica nada}';

    protected $description = 'Recalcula precio_categoria/subtotal/registration_totals de inscripciones pagadas que ya fueron editadas al menos una vez, para reparar el snapshot corrompido por un bug real (30/09/2026).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $registrationIds = AuditLog::query()->distinct()->pluck('registration_id');

        if ($registrationIds->isEmpty()) {
            $this->info('No hay inscripciones con ediciones registradas.');

            return self::SUCCESS;
        }

        $revisadas = 0;
        $corregidas = 0;

        Registration::whereIn('id', $registrationIds)
            ->where('pago_status', 'paid')
            ->chunkById(50, function ($registrations) use ($dryRun, &$revisadas, &$corregidas) {
                foreach ($registrations as $registration) {
                    $revisadas++;
                    $antes = $registration->totals()->first();
                    $grandTotalAntes = (float) ($antes->grand_total ?? 0);

                    $permitirCorregirCategoria = CajaMovimiento::where('registration_id', $registration->id)
                        ->where('tipo', 'edicion_pagada')
                        ->exists();

                    if ($dryRun) {
                        // Simula el recálculo sin persistir: se corre sobre
                        // una copia del modelo (misma referencia de BD, pero
                        // la Action real abajo solo actualiza si NO es dry-run).
                        $grandTotalRecalculado = $this->simularRecalculo($registration, $permitirCorregirCategoria);
                        if (round($grandTotalRecalculado, 2) !== round($grandTotalAntes, 2)) {
                            $corregidas++;
                            $this->line(" - {$registration->referencia}: grand_total {$grandTotalAntes} -> {$grandTotalRecalculado}");
                        }

                        continue;
                    }

                    SnapshotInscripcionPagadaData::recalcular($registration, $permitirCorregirCategoria);
                    $despues = $registration->totals()->first();
                    if (round((float) $despues->grand_total, 2) !== round($grandTotalAntes, 2)) {
                        $corregidas++;
                        $this->line(" - {$registration->referencia}: grand_total {$grandTotalAntes} -> {$despues->grand_total}");
                    }
                }
            });

        $verbo = $dryRun ? 'tendrían una corrección' : 'corregidas';
        $this->info("Revisadas: {$revisadas}. {$verbo}: {$corregidas}.");

        return self::SUCCESS;
    }

    /**
     * --dry-run no debe escribir nada — pero la fórmula real vive en
     * SnapshotInscripcionPagadaData, que SÍ persiste. Para no duplicar la
     * fórmula acá, se corre el recálculo real dentro de una transacción que
     * se revierte siempre (DB::transaction + throw controlado) — mismo
     * resultado que "simular", cero riesgo de que un dry-run deje algo
     * escrito por error.
     */
    private function simularRecalculo(Registration $registration, bool $permitirCorregirCategoria): float
    {
        $grandTotal = null;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($registration, $permitirCorregirCategoria, &$grandTotal) {
                SnapshotInscripcionPagadaData::recalcular($registration, $permitirCorregirCategoria);
                $grandTotal = (float) $registration->totals()->first()->grand_total;

                throw new \RuntimeException('dry-run: revertir');
            });
        } catch (\RuntimeException $e) {
            // Esperado — el throw de arriba es lo que fuerza el rollback.
        }

        return $grandTotal ?? 0.0;
    }
}
