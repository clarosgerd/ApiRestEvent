<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Registration;
use App\Models\Souvenir;

/**
 * Snapshot de una inscripción pagada (30/09/2026) — bug real confirmado con
 * 2 casos en producción: `ActualizarInscripcionPagadaAction` confiaba
 * ciegamente en `participante.precioCategoria`/`totales.inscripcion` que
 * manda el cliente para PERSISTIR `participantes.precio_categoria`/`subtotal`
 * y `registration_totals.inscripcion`/`talleres`/`fee`/`grand_total` — pero
 * `caja/_formulario.blade.php` transforma esos campos en una DIFERENCIA
 * (precio nuevo − precio ya pagado) para el resumen en pantalla, y esa misma
 * variable terminaba viajando al servidor como si fuera el precio absoluto.
 * El cobro real (`caja_movimientos`/`costo_adicion`) nunca estuvo mal —
 * se calcula aparte, 100% server-side — el problema era solo este snapshot.
 *
 * `recalcular()` reconstruye el snapshot completo desde el estado YA
 * PERSISTIDO (categoría real de cada participante, talleres/souvenirs/
 * donación ya guardados) — no necesita nada del payload del cliente, así
 * que protege por igual a Caja y a autoservicio, y sirve también para
 * reparar inscripciones ya corrompidas (ver
 * ReconciliarSnapshotInscripcionesPagadas).
 */
class SnapshotInscripcionPagadaData
{
    /**
     * @param bool $permitirCorregirCategoria Reconstruir precio_categoria/subtotal
     *   desde el catálogo cuando no coincide con ningún precio real de la
     *   categoría. Seguro SIEMPRE en el fix en caliente (se llama justo
     *   después de la edición real — "vigente hoy" = "vigente en el momento
     *   de la edición", sin ambigüedad). Para el barrido de reconciliación
     *   histórica (ver ReconciliarSnapshotInscripcionesPagadas) es
     *   responsabilidad del llamador decidir esto por evidencia (hubo un
     *   `caja_movimientos` tipo=edicion_pagada real) — "vigente hoy" puede
     *   ya no ser el precio que era real el día de una edición pasada, y
     *   confiar en el catálogo de hoy sin esa evidencia corrompería
     *   inscripciones que nunca pasaron por el bug real (dos falsos
     *   positivos encontrados en UAT el 02/10/2026: categoría con precio por
     *   período ya vencido, y categoría cuyo precio base simplemente subió
     *   después — ninguna de las dos pasó nunca por Caja).
     */
    public static function recalcular(Registration $registration, bool $permitirCorregirCategoria = true): void
    {
        $registration->loadMissing(['evento', 'participants.talleresSesiones', 'participants.souvenirParticipante']);

        $inscripcion = 0.0;
        foreach ($registration->participants as $participante) {
            $precioCategoriaFinal = (float) $participante->precio_categoria;

            // `participantes.categoria` no siempre es un id numérico real —
            // un form_type sin categoría real (staff/ponente/expositor, o
            // datos legado) guarda ahí su propio texto, no un id (mismo
            // criterio ya usado en ProvisionarCuentaExpositorAction::categoriaId()).
            // En ese caso no hay catálogo contra qué resolver — se confía en
            // lo ya persistido tal cual.
            if ($permitirCorregirCategoria && ctype_digit((string) $participante->categoria)) {
                $categoria = Category::find((int) $participante->categoria);
                if ($categoria) {
                    // Ojo: "vigente" acá significa HOY — correcto en el alta/edición
                    // en vivo, pero una categoría con precios por período (ver
                    // PrecioVigenteData) pudo haber tenido un precio distinto, igual
                    // de real, en el momento en que esta inscripción se pagó/editó.
                    // Por eso NO se compara solo contra el precio de hoy: se valida
                    // contra CUALQUIER precio que esa categoría haya tenido alguna
                    // vez (base + cada período) — si el valor guardado coincide con
                    // alguno, es un precio real de algún momento y se deja tal cual.
                    // Solo si no coincide con ninguno (el patrón real de corrupción:
                    // un delta tipo -1100 o 200 que no es el precio de nada) se
                    // reconstruye con el precio vigente de hoy, como mejor estimación
                    // disponible sin un historial de edición más preciso.
                    $preciosValidos = $categoria->pricePeriods->pluck('price')
                        ->push($categoria->price)
                        ->map(fn ($p) => round((float) $p, 2))
                        ->unique();

                    if (! $preciosValidos->contains(round($precioCategoriaFinal, 2))) {
                        $precioCategoriaFinal = (float) PrecioVigenteData::paraCategoria($categoria)['precio'];
                    }
                }
            }

            $inscripcion += $precioCategoriaFinal;

            // `subtotal` NO es solo el precio de categoría — el JS lo calcula
            // como precioCategoria + polera + souvenirs + donación - promo
            // (ver index.php, const subtotal) — se reconstruye siempre desde
            // esos mismos componentes YA persistidos (nunca se "preserva" un
            // subtotal viejo), tanto si precio_categoria se corrigió arriba
            // como si no: son dos inconsistencias independientes.
            $souvenirsParticipante = (float) $participante->souvenirParticipante->sum('precio');
            $subtotalReal = round(
                $precioCategoriaFinal + (float) $participante->precio_polera + $souvenirsParticipante
                + (float) $participante->donacion - (float) $participante->promo_descuento,
                2
            );

            if (round((float) $participante->precio_categoria, 2) !== round($precioCategoriaFinal, 2)
                || round((float) $participante->subtotal, 2) !== $subtotalReal) {
                $participante->update(['precio_categoria' => round($precioCategoriaFinal, 2), 'subtotal' => $subtotalReal]);
            }
        }

        $talleres = (float) $registration->participants->flatMap->talleresSesiones->sum('total');
        $souvenirs = (float) $registration->participants->flatMap->souvenirParticipante->sum('precio');
        $donacion = (float) $registration->participants->sum('donacion');

        // Mismo criterio/forma que CrearInscripcionAction::validateFeePct()
        // (fórmula del fee sobre el total ABSOLUTO de la inscripción, no
        // sobre un delta) — reconstruido acá con inputs ya resueltos en vez
        // de confiar en lo que mande el cliente.
        $souvenirIdsConCargo = Souvenir::where('aplica_cargo_servicio', true)->pluck('id');
        $souvenirsConCargo = (float) $registration->participants->flatMap->souvenirParticipante
            ->whereIn('souvenir_id', $souvenirIdsConCargo)
            ->sum('precio');

        $evento = $registration->evento;
        $baseFee = $inscripcion + ($evento->fee_incluye_talleres ? $talleres : 0) + $souvenirsConCargo;
        $fee = round($baseFee * (float) $evento->fee_pct, 2);

        // descuento/descuento_registrante (código promocional) se CONSERVAN
        // tal cual del registration_totals ya persistido — recalcular
        // descuentos de promo en una edición pagada es una pieza aparte, no
        // reportada como rota en los 2 casos reales que motivaron este fix.
        //
        // $registration->totals()->first() — NUNCA $registration->totals (la
        // propiedad): ActualizarInscripcionPagadaAction ya accede a
        // $registration->totals por propiedad ANTES de borrar y recrear la
        // fila (para costo_edicion_acumulado), lo que la cachea apuntando a
        // la fila VIEJA — leer por método fuerza una query fresca contra la
        // fila recién creada.
        $totalesActuales = $registration->totals()->first();
        $descuento = (float) ($totalesActuales->descuento ?? 0);
        $descuentoRegistrante = (float) ($totalesActuales->descuento_registrante ?? 0);

        $grandTotal = round($inscripcion + $talleres + $souvenirs + $donacion - $descuento - $descuentoRegistrante + $fee, 2);

        $registration->totals()->update([
            'inscripcion' => round($inscripcion, 2),
            'talleres'    => round($talleres, 2),
            'souvenirs'   => round($souvenirs, 2),
            'donacion'    => round($donacion, 2),
            'fee'         => $fee,
            'grand_total' => $grandTotal,
        ]);
    }
}
