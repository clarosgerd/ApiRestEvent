<?php

namespace App\Support;

use App\Models\Category;
use App\Models\CategoryPricePeriod;
use Carbon\CarbonImmutable;

/**
 * Precios por período (12/08/2026) — ver PRD-precios-periodos-fechas.md.
 *
 * Regla de "precio vigente" de una categoría, por orden de prioridad:
 * 1. Sin períodos configurados → `categories.price` (comportamiento
 *    actual, sin cambios — mismo criterio de compatibilidad que
 *    `item_stock` en kit/tallas/stock: sin filas = sin control).
 * 2. Hay un período cuyo rango [fecha_desde, fecha_hasta] contiene hoy
 *    → el precio de ese período.
 * 3. Ninguno vigente hoy, pero hay al menos uno ya vencido (su
 *    fecha_hasta ya pasó) → el precio del más reciente vencido —
 *    decisión del usuario: nunca se bloquea una venta por un hueco de
 *    configuración *entre* períodos o *después* del último.
 * 4. Ninguno vigente ni vencido (todos los períodos configurados son
 *    futuros, ej. se cargó la Preventa pero todavía no llegó su
 *    fecha_desde) → `categories.price` como último fallback — cierra el
 *    hueco lógico del caso "todavía no arrancó ningún período", que no
 *    es lo mismo que "sin períodos configurados" (caso 1) pero necesita
 *    el mismo fallback para no bloquear la venta.
 *
 * Aplica solo a categorías (`requiereCategoria=true`). Cuando
 * `requiereCategoria=false` el precio sale de `form_types.precio_base`
 * directo, sin pasar por acá — ver sección 0 del PRD.
 *
 * Precio USD fijo por período (20/08/2026) — `precio_usd` sigue la
 * MISMA selección de período que `precio` (mismo caso 1-4), pero lee
 * `price_usd` de ese período en vez de `price`. Si el período elegido no
 * tiene `price_usd` cargado (el organizador todavía no lo completó, o
 * nunca lo va a usar), cae a `categories.price_usd` — igual que
 * `categories.price` es el fallback de `precio` en los casos 1 y 4.
 * `null` si ni el período ni la categoría tienen USD cargado (evento que
 * no vende en USD fijo). Ver CurrencyResolverData::resolverPrecioFijo().
 */
class PrecioVigenteData
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
    public static function paraCategoria(Category $category): array
    {
        $periodos = $category->pricePeriods()->orderBy('fecha_desde')->get();

        $periodosArray = $periodos->map(fn (CategoryPricePeriod $p) => [
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
                'precio'              => (float) $category->price,
                'precio_usd'          => $category->price_usd !== null ? (float) $category->price_usd : null,
                'periodo_nombre'      => null,
                'periodo_fecha_hasta' => null,
                'periodos'            => $periodosArray,
            ];
        }

        $hoy = CarbonImmutable::today();

        // Caso 2: hay un período vigente hoy.
        $vigente = $periodos->first(
            fn (CategoryPricePeriod $p) => ! $hoy->lt($p->fecha_desde) && ! $hoy->gt($p->fecha_hasta)
        );

        if ($vigente) {
            return [
                'precio'              => (float) $vigente->price,
                'precio_usd'          => self::precioUsdDelPeriodo($vigente, $category),
                'periodo_nombre'      => $vigente->nombre,
                'periodo_fecha_hasta' => $vigente->fecha_hasta->toDateString(),
                'periodos'            => $periodosArray,
            ];
        }

        // Caso 3: ninguno vigente, pero hay al menos uno ya vencido — el
        // más reciente vencido (mayor fecha_hasta entre los que ya pasaron).
        $vencidoMasReciente = $periodos
            ->filter(fn (CategoryPricePeriod $p) => $hoy->gt($p->fecha_hasta))
            ->sortByDesc(fn (CategoryPricePeriod $p) => $p->fecha_hasta->timestamp)
            ->first();

        if ($vencidoMasReciente) {
            return [
                'precio'              => (float) $vencidoMasReciente->price,
                'precio_usd'          => self::precioUsdDelPeriodo($vencidoMasReciente, $category),
                'periodo_nombre'      => $vencidoMasReciente->nombre,
                'periodo_fecha_hasta' => $vencidoMasReciente->fecha_hasta->toDateString(),
                'periodos'            => $periodosArray,
            ];
        }

        // Caso 4: todos los períodos configurados son futuros → fallback
        // a categories.price, mismo que el caso "sin períodos".
        return [
            'precio'              => (float) $category->price,
            'precio_usd'          => $category->price_usd !== null ? (float) $category->price_usd : null,
            'periodo_nombre'      => null,
            'periodo_fecha_hasta' => null,
            'periodos'            => $periodosArray,
        ];
    }

    /**
     * `price_usd` del período si lo tiene cargado, si no cae a
     * `categories.price_usd` — ver docblock de la clase.
     */
    private static function precioUsdDelPeriodo(CategoryPricePeriod $periodo, Category $category): ?float
    {
        if ($periodo->price_usd !== null) {
            return (float) $periodo->price_usd;
        }

        return $category->price_usd !== null ? (float) $category->price_usd : null;
    }
}
