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
 * 2. Si la sesión tiene `precio` propio (override) → ese.
 * 3. Si no, hereda del taller (`taller.precio`).
 * 4. Si tampoco hay precio en el taller → 0.
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

        if ($taller->precio !== null) {
            return (float) $taller->precio;
        }

        return 0.0;
    }

    /**
     * Total = unit_price - discount. Hoy el descuento de taller siempre
     * es 0 (las promociones existentes no aplican a talleres, decisión
     * T2), pero la función queda lista para reglas futuras.
     */
    public static function total(Taller $taller, SesionCongreso $sesion, Evento $evento): float
    {
        $unit = self::unitPrice($taller, $sesion, $evento);
        $discount = 0.0;

        return round($unit - $discount, 2);
    }
}