<?php

namespace App\Actions;

use App\Models\Evento;
use App\Models\Liquidacion;
use App\Models\LiquidacionDetalle;
use App\Models\Registration;
use App\Models\Socio;
use App\Services\AdminAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Consolidación financiera — liquidación de utilidades entre socios. Ver
 * elascenso/event/brain/ (sesión 11/08/2026) y
 * ApiRestEvent/brain/api_rest_event/PRD-Consolidacion-only-superadmin.md.
 *
 * Reparte el service fee ya cobrado (`registration_totals.fee`, el 5% que
 * se cobra hoy por cada inscripción `paid`) entre los `Socio` activos,
 * según su `porcentaje`. No mueve dinero real ni integra ninguna
 * pasarela — solo calcula y deja un registro auditable del reparto.
 */
class LiquidarEventoAction
{
    /**
     * Calcula lo que le tocaría a cada socio si se liquidara este evento
     * ahora — SIN persistir nada. Usado por el preview del panel y
     * reusado internamente por handle(). A propósito no lanza excepción
     * por reglas de negocio (evento no cerrado, socios que no suman
     * 100%) — esas solo bloquean handle(); el preview siempre debe poder
     * mostrar el cálculo (o la falta de socios) para que el superadmin
     * vea qué falta arreglar antes de liquidar.
     *
     * @return array{monto_base: float, cantidad_inscripciones: int, porcentaje_total: float, detalles: array<int, array{socio_id: int, socio_nombre: string, porcentaje: float, monto: float}>}
     */
    public function calcular(Evento $evento): array
    {
        $montoBase = round((float) Registration::query()
            ->join('registration_totals', 'registration_totals.registration_id', '=', 'registrations.id')
            ->where('registrations.evento_id', $evento->id)
            ->where('registrations.pago_status', 'paid')
            ->sum('registration_totals.fee'), 2);

        $cantidadInscripciones = Registration::where('evento_id', $evento->id)
            ->where('pago_status', 'paid')
            ->count();

        $socios = Socio::where('activo', true)->orderBy('id')->get();
        $porcentajeTotal = round((float) $socios->sum('porcentaje'), 2);

        $detalles = [];
        $montoAsignado = 0.0;
        $ultimoIndex = $socios->count() - 1;

        foreach ($socios as $index => $socio) {
            if ($index === $ultimoIndex) {
                // El último socio absorbe el residuo de redondeo, para que
                // la suma de los detalles sea exactamente igual a
                // monto_base centavo a centavo.
                $monto = round($montoBase - $montoAsignado, 2);
            } else {
                $monto = round($montoBase * ((float) $socio->porcentaje / 100), 2);
                $montoAsignado += $monto;
            }

            $detalles[] = [
                'socio_id' => $socio->id,
                'socio_nombre' => $socio->nombre,
                'porcentaje' => (float) $socio->porcentaje,
                'monto' => $monto,
            ];
        }

        return [
            'monto_base' => $montoBase,
            'cantidad_inscripciones' => $cantidadInscripciones,
            'porcentaje_total' => $porcentajeTotal,
            'detalles' => $detalles,
        ];
    }

    /**
     * Confirma y persiste la liquidación. El controller es responsable de
     * chequear antes si ya existe una Liquidacion para este evento (409,
     * mismo patrón que EventoController::despublicar() con
     * registrations()->exists()) — acá solo se validan las reglas de
     * negocio que sí son responsabilidad del cálculo mismo.
     */
    public function handle(Evento $evento): Liquidacion
    {
        if ($evento->estado_evento_id !== 'closed') {
            throw new \DomainException('El evento debe estar cerrado antes de poder liquidarlo.');
        }

        $calculo = $this->calcular($evento);

        if (empty($calculo['detalles'])) {
            throw new \DomainException('No hay socios activos configurados — no se puede liquidar ningún evento.');
        }

        if (abs($calculo['porcentaje_total'] - 100.0) > 0.01) {
            throw new \DomainException(
                "Los porcentajes de los socios activos suman {$calculo['porcentaje_total']}%, deben sumar exactamente 100% antes de poder liquidar."
            );
        }

        return DB::transaction(function () use ($evento, $calculo) {
            $liquidacion = Liquidacion::create([
                'evento_id' => $evento->id,
                'monto_base' => $calculo['monto_base'],
                'cantidad_inscripciones' => $calculo['cantidad_inscripciones'],
                'liquidado_por_admin_user_id' => auth('admins')->id(),
                'liquidado_en' => now(),
            ]);

            foreach ($calculo['detalles'] as $detalle) {
                LiquidacionDetalle::create([
                    'liquidacion_id' => $liquidacion->id,
                    'socio_id' => $detalle['socio_id'],
                    'socio_nombre' => $detalle['socio_nombre'],
                    'porcentaje' => $detalle['porcentaje'],
                    'monto' => $detalle['monto'],
                ]);
            }

            $liquidacion->load('detalles');

            AdminAuditLogger::log('liquidar', 'Liquidacion', $liquidacion->id, $evento->id, null, $liquidacion->toArray());

            return $liquidacion;
        });
    }
}
