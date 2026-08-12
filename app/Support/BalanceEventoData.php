<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\PresupuestoEvento;
use App\Models\Registration;

/**
 * Balance financiero de un evento (presupuesto del organizador) — ver
 * PRD-presupuesto_de_un_evento.md y elascenso/event/brain/ (sesión
 * 11/08/2026). Archivo hermano de DashboardInscripcionesData (mismo
 * patrón de fachada estática `paraEvento()`), separado a propósito: ese
 * archivo solo cuenta inscripciones, este suma dinero — no se mezclan.
 *
 * Distinto de LiquidarEventoAction (Liquidación de utilidades entre
 * socios): acá `ingresosInscripciones` es el neto que le queda al
 * ORGANIZADOR (sin el service fee, que nunca llega a sus manos — se
 * suma arriba del precio que paga el participante, ver
 * `review-payment.js`), mientras que Liquidación reparte justamente ese
 * fee entre los socios de PassToGo. No hay relación de cálculo entre
 * ambas.
 */
class BalanceEventoData
{
    public static function paraEvento(Evento $evento): array
    {
        $ingresosInscripciones = round((float) Registration::query()
            ->join('registration_totals', 'registration_totals.registration_id', '=', 'registrations.id')
            ->where('registrations.evento_id', $evento->id)
            ->where('registrations.pago_status', 'paid')
            ->selectRaw('SUM(inscripcion + donacion + souvenirs - descuento - descuento_registrante) as neto')
            ->value('neto'), 2);

        $ingresosManuales = round((float) PresupuestoEvento::where('evento_id', $evento->id)
            ->where('tipo', 'ingreso')
            ->sum('monto'), 2);

        $gastosManuales = round((float) PresupuestoEvento::where('evento_id', $evento->id)
            ->where('tipo', 'gasto')
            ->sum('monto'), 2);

        $ingresosTotales = round($ingresosInscripciones + $ingresosManuales, 2);

        return [
            'ingresosInscripciones' => $ingresosInscripciones,
            'ingresosManuales' => $ingresosManuales,
            'gastosManuales' => $gastosManuales,
            'ingresosTotales' => $ingresosTotales,
            'utilidadNeta' => round($ingresosTotales - $gastosManuales, 2),
        ];
    }
}
