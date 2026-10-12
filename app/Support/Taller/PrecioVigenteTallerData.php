<?php

namespace App\Support\Taller;

use App\Models\Taller;
use App\Models\TallerPricePeriod;
use Carbon\CarbonImmutable;

/**
 * Precios por período para talleres (12/10/2026) — mismo patrón que
 * App\Support\PrecioVigenteData (categorías), aplicado a `talleres`.
 *
 * Regla de "precio vigente" de un taller, por orden de prioridad:
 * 1. Sin períodos configurados → `talleres.precio`/`price_usd` (sin cambios).
 * 2. Hay un período cuyo rango [fecha_desde, fecha_hasta] contiene hoy →
 *    el precio de ese período.
 * 3. Ninguno vigente hoy, pero hay al menos uno ya vencido → el precio del
 *    más reciente vencido (nunca se bloquea una venta por un hueco de
 *    configuración entre períodos o después del último).
 * 4. Ninguno vigente ni vencido (todos futuros) → fallback a
 *    `talleres.precio`/`price_usd`.
 *
 * Importante: esto resuelve el precio BASE del taller. El override de
 * sesión (`sesiones_congreso.precio`) sigue ganando siempre por encima de
 * esto — ver ResolverPrecioTallerData::unitPrice(), que solo llama a esta
 * clase cuando la sesión no tiene override cargado.
 */
class PrecioVigenteTallerData
{
    /**
     * @return array{
     *   precio: float,
     *   precio_usd: ?float,
     *   periodo_nombre: ?string,
     *   periodo_fecha_hasta: ?string,
     *   periodos: array<int, array{id:int, nombre:string, price:float, price_usd:?float, fecha_desde:string, fecha_hasta:string}>
     * }
     */
    public static function paraTaller(Taller $taller): array
    {
        $periodos = $taller->pricePeriods()->orderBy('fecha_desde')->get();

        $periodosArray = $periodos->map(fn (TallerPricePeriod $p) => [
            'id'          => $p->id,
            'nombre'      => $p->nombre,
            'price'       => (float) $p->price,
            'price_usd'   => $p->price_usd !== null ? (float) $p->price_usd : null,
            'fecha_desde' => $p->fecha_desde->toDateString(),
            'fecha_hasta' => $p->fecha_hasta->toDateString(),
        ])->values()->all();

        // Caso 1: sin períodos configurados.
        if ($periodos->isEmpty()) {
            return [
                'precio'              => (float) ($taller->precio ?? 0),
                'precio_usd'          => $taller->price_usd !== null ? (float) $taller->price_usd : null,
                'periodo_nombre'      => null,
                'periodo_fecha_hasta' => null,
                'periodos'            => $periodosArray,
            ];
        }

        $hoy = CarbonImmutable::today();

        // Caso 2: hay un período vigente hoy.
        $vigente = $periodos->first(
            fn (TallerPricePeriod $p) => ! $hoy->lt($p->fecha_desde) && ! $hoy->gt($p->fecha_hasta)
        );

        if ($vigente) {
            return [
                'precio'              => (float) $vigente->price,
                'precio_usd'          => self::precioUsdDelPeriodo($vigente, $taller),
                'periodo_nombre'      => $vigente->nombre,
                'periodo_fecha_hasta' => $vigente->fecha_hasta->toDateString(),
                'periodos'            => $periodosArray,
            ];
        }

        // Caso 3: ninguno vigente, pero hay al menos uno ya vencido — el
        // más reciente vencido (mayor fecha_hasta entre los que ya pasaron).
        $vencidoMasReciente = $periodos
            ->filter(fn (TallerPricePeriod $p) => $hoy->gt($p->fecha_hasta))
            ->sortByDesc(fn (TallerPricePeriod $p) => $p->fecha_hasta->timestamp)
            ->first();

        if ($vencidoMasReciente) {
            return [
                'precio'              => (float) $vencidoMasReciente->price,
                'precio_usd'          => self::precioUsdDelPeriodo($vencidoMasReciente, $taller),
                'periodo_nombre'      => $vencidoMasReciente->nombre,
                'periodo_fecha_hasta' => $vencidoMasReciente->fecha_hasta->toDateString(),
                'periodos'            => $periodosArray,
            ];
        }

        // Caso 4: todos los períodos configurados son futuros → fallback
        // a talleres.precio, mismo que el caso "sin períodos".
        return [
            'precio'              => (float) ($taller->precio ?? 0),
            'precio_usd'          => $taller->price_usd !== null ? (float) $taller->price_usd : null,
            'periodo_nombre'      => null,
            'periodo_fecha_hasta' => null,
            'periodos'            => $periodosArray,
        ];
    }

    /**
     * `price_usd` del período si lo tiene cargado, si no cae a
     * `talleres.price_usd` — ver docblock de la clase.
     */
    private static function precioUsdDelPeriodo(TallerPricePeriod $periodo, Taller $taller): ?float
    {
        if ($periodo->price_usd !== null) {
            return (float) $periodo->price_usd;
        }

        return $taller->price_usd !== null ? (float) $taller->price_usd : null;
    }
}
