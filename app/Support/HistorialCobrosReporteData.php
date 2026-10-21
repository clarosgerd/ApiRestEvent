<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\Registration;

/**
 * Reporte de historial de cobros por inscripción (22/10/2026, pedido real
 * del usuario) — para cada inscripción pagada del evento, la secuencia
 * cronológica de movimientos: el alta inicial y cada adición en
 * autoservicio (agregar taller/categoría sobre una inscripción ya pagada,
 * ver PagoAdicionalInscripcion/ActualizarInscripcionPagadaAction), con el
 * monto de cada movimiento desglosado en contenido nuevo / cargo de
 * servicio (fee) / costo de edición fijo. Mismo patrón que
 * PromoCodeReporteData: por-un-evento, sin paginar.
 *
 * El reto real: `registration_totals` se borra y se recrea con el estado
 * ACUMULADO cada vez que ActualizarInscripcionPagadaAction corre (ver esa
 * Action), así que para una inscripción con adiciones ya no representa el
 * estado "tal cual se inscribió" — solo el final. `monto` de cada
 * PagoAdicionalInscripcion SÍ es exacto (costo_edicion + delta de
 * contenido + fee de esa transacción puntual, ver
 * CalcularCostoAdicionalAction), y cada una trae su propio
 * `totales_payload` (el estado COMPLETO justo después de aplicarse).
 *
 * De ahí sale que el grand_total "inicial" (antes de la 1ra edición) SÍ se
 * puede despejar exacto por resta (es una suma lineal, no hace falta saber
 * cómo se repartió esa 1ra edición entre contenido y fee):
 *   grand_total_inicial = totales_payload[0].grand_total - (monto[0] - costo_edicion)
 * Pero el REPARTO fee/contenido de esa 1ra edición específicamente no
 * queda registrado en ningún lado — se estima proporcional a fee_pct del
 * evento (puede no ser exacto, incluso dar fee negativo si esa edición en
 * particular no siguió el reparto estándar — se marca `esEstimado` para
 * que quien lo lea sepa que esa fila puntual es aproximada). A partir de
 * la 2da adición en adelante todo es exacto (cada una trae tanto su propio
 * totales_payload como el de la anterior).
 */
class HistorialCobrosReporteData
{
    public static function paraEvento(Evento $evento): array
    {
        $feePct = (float) ($evento->fee_pct ?? 0.05);

        $registrations = Registration::where('evento_id', $evento->id)
            ->where('pago_status', 'paid')
            ->with(['totals', 'formType', 'participants', 'pagosAdicionales' => function ($q) {
                $q->where('pago_status', 'paid')->orderBy('paid_at');
            }])
            ->orderBy('fecha')
            ->get();

        $filas = [];
        $totalConAdiciones = 0;
        $totalAnomalias = 0;
        $totalCobrado = 0.0;
        $totalFee = 0.0;
        $totalCostoEdicion = 0.0;

        foreach ($registrations as $reg) {
            $principal = $reg->participants->first();
            $nombrePrincipal = $principal ? trim($principal->nombre.' '.$principal->apellido) : null;
            $costoEdicion = (float) ($reg->formType->costo_edicion ?? 0);
            $pagos = $reg->pagosAdicionales;

            if ($pagos->isEmpty()) {
                $grandTotalInicial = (float) ($reg->totals->grand_total ?? 0);
                $feeInicial = (float) ($reg->totals->fee ?? 0);

                $filas[] = [
                    'referenciaInscripcion' => $reg->referencia,
                    'tipoMovimiento' => 'inicial',
                    'referenciaMovimiento' => $reg->referencia,
                    'fechaMovimiento' => optional($reg->fecha)->toIso8601String(),
                    'monto' => $grandTotalInicial,
                    'fee' => $feeInicial,
                    'costoEdicion' => 0.0,
                    'contenidoNuevo' => round($grandTotalInicial - $feeInicial, 2),
                    'esEstimado' => false,
                    'grandTotalAcumulado' => $grandTotalInicial,
                    'participantePrincipal' => $nombrePrincipal,
                    'anomalias' => [],
                ];
                $totalCobrado += $grandTotalInicial;
                $totalFee += $feeInicial;

                continue;
            }

            $totalConAdiciones++;

            $primerPago = $pagos->first();
            $xPrimero = round((float) $primerPago->monto - $costoEdicion, 2);
            $grandTotalInicial = round((float) ($primerPago->totales_payload['grand_total'] ?? 0) - $xPrimero, 2);
            $feeDelPrimero = round($xPrimero * ($feePct / (1 + $feePct)), 2);
            $feeInicial = round((float) ($primerPago->totales_payload['fee'] ?? 0) - $feeDelPrimero, 2);

            $filas[] = [
                'referenciaInscripcion' => $reg->referencia,
                'tipoMovimiento' => 'inicial',
                'referenciaMovimiento' => $reg->referencia,
                'fechaMovimiento' => optional($reg->fecha)->toIso8601String(),
                'monto' => $grandTotalInicial,
                'fee' => $feeInicial,
                'costoEdicion' => 0.0,
                'contenidoNuevo' => round($grandTotalInicial - $feeInicial, 2),
                'esEstimado' => true,
                'grandTotalAcumulado' => $grandTotalInicial,
                'participantePrincipal' => $nombrePrincipal,
                'anomalias' => [],
            ];
            $totalCobrado += $grandTotalInicial;
            $totalFee += $feeInicial;

            $feeAcumulado = $feeInicial;

            foreach ($pagos as $idx => $pago) {
                $payload = $pago->totales_payload ?? [];
                $feeNuevo = (float) ($payload['fee'] ?? $feeAcumulado);
                $grandTotalNuevo = (float) ($payload['grand_total'] ?? $grandTotalInicial);
                $esEstimado = $idx === 0;
                $feeDelMovimiento = round($feeNuevo - $feeAcumulado, 2);
                $contenidoNuevo = round((float) $pago->monto - $costoEdicion - $feeDelMovimiento, 2);

                $anomalias = [];
                if ($pago->paid_at && $reg->fecha && $pago->paid_at->lt($reg->fecha)) {
                    $anomalias[] = 'fecha_incoherente';
                }
                if (! preg_match('/^AD-[A-Z0-9]{8}$/', $pago->referencia)) {
                    $anomalias[] = 'referencia_no_estandar';
                }
                if (! empty($anomalias)) {
                    $totalAnomalias++;
                }

                $filas[] = [
                    'referenciaInscripcion' => $reg->referencia,
                    'tipoMovimiento' => 'adicion',
                    'referenciaMovimiento' => $pago->referencia,
                    'fechaMovimiento' => optional($pago->paid_at)->toIso8601String(),
                    'monto' => (float) $pago->monto,
                    'fee' => $feeDelMovimiento,
                    'costoEdicion' => $costoEdicion,
                    'contenidoNuevo' => $contenidoNuevo,
                    'esEstimado' => $esEstimado,
                    'grandTotalAcumulado' => $grandTotalNuevo,
                    'participantePrincipal' => $nombrePrincipal,
                    'anomalias' => $anomalias,
                ];
                $totalCobrado += (float) $pago->monto;
                $totalFee += $feeDelMovimiento;
                $totalCostoEdicion += $costoEdicion;

                $feeAcumulado = $feeNuevo;
            }
        }

        return [
            'filas' => $filas,
            'resumen' => [
                'totalInscripciones' => $registrations->count(),
                'totalConAdiciones' => $totalConAdiciones,
                'totalCobrado' => round($totalCobrado, 2),
                'totalFee' => round($totalFee, 2),
                'totalCostoEdicion' => round($totalCostoEdicion, 2),
                'totalAnomalias' => $totalAnomalias,
            ],
        ];
    }
}
