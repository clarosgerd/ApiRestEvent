<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Hora real de Bolivia para reportes/resources (21/10/2026, pedido real del
 * usuario) — `created_at`/`updated_at` (y cualquier timestamp que pone la
 * app con `now()`) son UTC de verdad (`config('app.timezone')`), pero los
 * reportes los mostraban crudos sin convertir: alguien en Bolivia leía la
 * hora UTC pensando que era la hora real, 4 horas adelantada. Confirmado
 * contra un caso real (`registrations.created_at` convertido a
 * America/La_Paz coincide exactamente con `registrations.fecha`, que sí es
 * la hora real porque la manda el navegador del participante).
 *
 * `registrations.fecha` es un caso DISTINTO — no pasa por acá — no es UTC
 * de verdad (nadie la calcula con `now()`, la manda el navegador sin zona
 * declarada), así que convertirla con este helper la rompería en vez de
 * arreglarla.
 */
class FechaReporteData
{
    private const ZONA_BOLIVIA = 'America/La_Paz';

    public static function iso(?CarbonInterface $fecha): ?string
    {
        return $fecha?->copy()->setTimezone(self::ZONA_BOLIVIA)->toIso8601String();
    }
}
