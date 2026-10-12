<?php

namespace App\Support\Taller;

use App\Models\Evento;
use App\Models\SesionCongreso;
use App\Models\Taller;

/**
 * Resolución del precio efectivo de una sesión de taller. Reglas
 * (18/08/2026) — ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md §1.5 y
 * decisión T6:
 *
 * 1. Si el evento NO tiene `talleres_con_costo` → el precio SIEMPRE es
 *    0 (los talleres son seleccionables pero no suman al total).
 * 2. Si la sesión tiene `precio` propio (override) → ese. El override de
 *    sesión GANA SIEMPRE, incluso por encima de un período de precio por
 *    fecha del taller (12/10/2026) — un override es una decisión manual
 *    explícita del organizador sobre esa sesión puntual; dejarlo como
 *    techo evita que un período que arranca lo pise en silencio.
 * 3. Si no hay override, precio vigente del taller — ver
 *    PrecioVigenteTallerData::paraTaller() (con períodos por fecha, o
 *    `taller.precio` si no tiene ninguno configurado).
 * 4. Si tampoco hay precio → 0.
 *
 * El backend es la autoridad: el cliente puede mandar `unit_price` en el
 * payload, pero `CrearInscripcionAction` lo IGNORA y siempre recalcula
 * con esta clase (mismo criterio que `precioCategoria` y `fee`).
 */
class ResolverPrecioTallerData
{
    /**
     * Devuelve el precio unitario (sin descuento) que se va a persistir
     * como snapshot en `participante_taller_sesion.unit_price`.
     */
    public static function unitPrice(Taller $taller, SesionCongreso $sesion, Evento $evento): float
    {
        if (! $evento->talleres_con_costo) {
            return 0.0;
        }

        if ($sesion->precio !== null) {
            return (float) $sesion->precio;
        }

        return PrecioVigenteTallerData::paraTaller($taller)['precio'];
    }

    /**
     * Total = unit_price - discount. El descuento de combo (12/10/2026,
     * ver ResolverCombosTallerAction) se aplica APARTE, después de llamar
     * a esta función, nunca acá — este método sigue siendo "precio
     * individual sin ninguna promoción", igual que antes.
     */
    public static function total(Taller $taller, SesionCongreso $sesion, Evento $evento): float
    {
        $unit = self::unitPrice($taller, $sesion, $evento);
        $discount = 0.0;

        return round($unit - $discount, 2);
    }

    /**
     * Precio USD fijo efectivo (19/08/2026) — usado solo cuando el evento
     * tiene `usd_precio_fijo=true` (ver CurrencyResolverData::resolverPrecioFijo()).
     * Mismo patrón override que unitPrice(): sesión gana, si no, precio
     * vigente del taller (período por fecha o `taller.price_usd`). A
     * diferencia de unitPrice() (que cae a 0.0 cuando falta el precio),
     * acá se distingue "gratis" de "falta cargar el precio":
     *
     * - talleres_con_costo=false → 0.0 (el taller es gratis igual en USD).
     * - talleres_con_costo=true y ninguno tiene price_usd → null (el
     *   caller debe rechazar la inscripción, no cobrar $0 en silencio).
     */
    public static function unitPriceUsd(Taller $taller, SesionCongreso $sesion, Evento $evento): ?float
    {
        if (! $evento->talleres_con_costo) {
            return 0.0;
        }

        if ($sesion->price_usd !== null) {
            return (float) $sesion->price_usd;
        }

        return PrecioVigenteTallerData::paraTaller($taller)['precio_usd'];
    }
}