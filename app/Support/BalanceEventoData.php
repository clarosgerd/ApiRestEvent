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
        // Bug real encontrado el 04/09/2026: no sumaba `talleres` — todo
        // evento con talleres subestimaba su recaudación real. `talleres`
        // se agregó a `registration_totals` el 18/08/2026 (congresos con
        // talleres) sin actualizar este SUM.
        $ingresosInscripciones = round((float) Registration::query()
            ->join('registration_totals', 'registration_totals.registration_id', '=', 'registrations.id')
            ->where('registrations.evento_id', $evento->id)
            ->where('registrations.pago_status', 'paid')
            ->selectRaw('SUM(inscripcion + donacion + souvenirs + talleres - descuento - descuento_registrante) as neto')
            ->value('neto'), 2);

        // Ingresos por ediciones de inscripciones pagadas (04/09/2026) —
        // pedido del usuario: "no tenemos información... por cambios de
        // inscripción". Solo el cargo FIJO de edición (costo_edicion,
        // acumulado en registration_totals.costo_edicion_acumulado) — la
        // diferencia de precio de la edición (categoría/souvenirs/talleres)
        // YA está contada arriba, porque esas columnas reflejan el estado
        // ACTUAL (post-edición). Sumar el costo_adicion completo (como
        // vive en CajaMovimiento/PagoAdicionalInscripcion) duplicaría esa
        // diferencia — por eso se usa esta columna dedicada, no esas
        // tablas. Ediciones anteriores a este cambio quedan en 0 (no se
        // pueden reconstruir con precisión) — limitación conocida, no bug.
        $ingresosEdiciones = round((float) Registration::query()
            ->join('registration_totals', 'registration_totals.registration_id', '=', 'registrations.id')
            ->where('registrations.evento_id', $evento->id)
            ->where('registrations.pago_status', 'paid')
            ->sum('registration_totals.costo_edicion_acumulado'), 2);

        $ingresosManuales = round((float) PresupuestoEvento::where('evento_id', $evento->id)
            ->where('tipo', 'ingreso')
            ->sum('monto'), 2);

        $gastosManuales = round((float) PresupuestoEvento::where('evento_id', $evento->id)
            ->where('tipo', 'gasto')
            ->sum('monto'), 2);

        $ingresosTotales = round($ingresosInscripciones + $ingresosEdiciones + $ingresosManuales, 2);

        return [
            'ingresosInscripciones' => $ingresosInscripciones,
            'ingresosEdiciones' => $ingresosEdiciones,
            'ingresosManuales' => $ingresosManuales,
            'gastosManuales' => $gastosManuales,
            'ingresosTotales' => $ingresosTotales,
            'utilidadNeta' => round($ingresosTotales - $gastosManuales, 2),
        ];
    }
}
