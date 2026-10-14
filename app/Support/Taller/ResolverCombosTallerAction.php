<?php

namespace App\Support\Taller;

use App\Models\Evento;
use App\Models\TallerCombo;

/**
 * Matching y reparto de combos de talleres (12/10/2026, pedido del
 * usuario) — ej. "Taller A + Taller B por el precio de 1" o "por $300
 * fijo". Recibe SOLO selecciones NUEVAS de un mismo participante, ya
 * resueltas en `unitPrice` por ResolverPrecioTallerData — nunca
 * selecciones ya persistidas de una edición anterior (ver snapshot-lock
 * en RegistrationService::createParticipantFromData(), que es lo que
 * garantiza que un combo nunca se aplica retroactivo sobre algo ya
 * cobrado).
 *
 * Reglas:
 * - Un combo se aplica si TODOS sus talleres miembro están entre las
 *   selecciones (subconjunto, no igualdad exacta) — talleres adicionales
 *   sueltos quedan a precio normal.
 * - Si el participante seleccionó más de una sesión del mismo taller, solo
 *   la de menor `unitPrice` participa del matching de combo; la otra
 *   queda siempre a precio normal fuera de cualquier combo.
 * - Si varios combos son aplicables a la vez, se aceptan en orden de mayor
 *   ahorro total primero (`suma de unitPrice de sus talleres - precio del
 *   combo`), sin reutilizar un taller ya "consumido" por un combo ya
 *   aceptado en esta misma resolución (greedy).
 * - El descuento de un combo aceptado se reparte proporcional al
 *   `unitPrice` de cada taller miembro, con el resto de redondeo
 *   absorbido por el último ítem para que la suma cierre exacta.
 * - Precio por categoría (13/10/2026, pedido del usuario): el mismo combo
 *   (mismos talleres) puede cobrar distinto según la categoría del
 *   participante — ver TallerCombo::precioParaCategoria()/
 *   precioUsdParaCategoria(). Un combo sin ninguna fila de precio
 *   aplicable a `$categoriaId` (ni una específica ni la default) no es
 *   candidato en absoluto, no "sin descuento".
 * - USD fijo (13/10/2026, pedido del usuario): `resolverUsd()` hace
 *   exactamente el mismo matching/reparto, pero sobre `unitPrice` en USD
 *   y el precio del combo en USD — independiente del resultado en Bs
 *   (mismo criterio ya existente para talleres individuales: BOB y USD
 *   fijo son dos cálculos en paralelo, nunca uno depende del otro). Un
 *   combo cuya fila de precio no tiene `price_usd` cargado tampoco es
 *   candidato acá, igual que un taller sin precio USD — nunca se cobra de
 *   menos en silencio, el caller (CurrencyResolverData::resolverPrecioFijo())
 *   debe rechazar si corresponde.
 */
class ResolverCombosTallerAction
{
    /**
     * @param  array<int, array{sesionId:int, tallerId:int, unitPrice:float}>  $selecciones
     * @return array<int, array{sesionId:int, tallerId:int, unitPrice:float, discount:float, total:float, tallerComboId:?int}>
     */
    public static function resolver(array $selecciones, Evento $evento, ?string $categoriaId = null): array
    {
        return self::resolverGenerico(
            $selecciones,
            $evento,
            fn (TallerCombo $c) => $c->precioParaCategoria($categoriaId)
        );
    }

    /**
     * @param  array<int, array{sesionId:int, tallerId:int, unitPrice:float}>  $selecciones unitPrice en USD
     * @return array<int, array{sesionId:int, tallerId:int, unitPrice:float, discount:float, total:float, tallerComboId:?int}>
     */
    public static function resolverUsd(array $selecciones, Evento $evento, ?string $categoriaId = null): array
    {
        return self::resolverGenerico(
            $selecciones,
            $evento,
            fn (TallerCombo $c) => $c->precioUsdParaCategoria($categoriaId)
        );
    }

    /**
     * @param  array<int, array{sesionId:int, tallerId:int, unitPrice:float}>  $selecciones
     * @param  \Closure(TallerCombo): ?float  $precioDelCombo resuelve el precio aplicable de un combo (Bs o USD,
     *   ya filtrado por categoría) — `null` si ese combo no aplica.
     */
    private static function resolverGenerico(array $selecciones, Evento $evento, \Closure $precioDelCombo): array
    {
        $resultado = [];
        foreach ($selecciones as $i => $s) {
            $resultado[$i] = $s + [
                'discount' => 0.0,
                'total' => round($s['unitPrice'], 2),
                'tallerComboId' => null,
            ];
        }

        $combos = TallerCombo::where('evento_id', $evento->id)->with(['talleres', 'precios'])->get()
            ->filter(fn (TallerCombo $c) => $c->vigenteHoy() && $precioDelCombo($c) !== null)
            ->values();

        if ($combos->isEmpty()) {
            return $resultado;
        }

        // Para cada taller seleccionado, el índice de su selección más
        // barata (representante para el matching de combo) — si el
        // participante elige 2 sesiones del mismo taller, la otra queda
        // siempre fuera del combo, a precio normal.
        $indicePorTaller = [];
        foreach ($selecciones as $i => $s) {
            $tallerId = $s['tallerId'];
            if (! isset($indicePorTaller[$tallerId])
                || $s['unitPrice'] < $selecciones[$indicePorTaller[$tallerId]]['unitPrice']) {
                $indicePorTaller[$tallerId] = $i;
            }
        }

        // Candidatos: combos cuyos talleres miembro son subconjunto de los
        // talleres presentes, con su ahorro total.
        $candidatos = [];
        foreach ($combos as $combo) {
            $tallerIdsCombo = $combo->talleres->pluck('id')->sort()->values()->all();

            if (empty($tallerIdsCombo)) {
                continue;
            }

            $todosPresentes = true;
            foreach ($tallerIdsCombo as $tid) {
                if (! isset($indicePorTaller[$tid])) {
                    $todosPresentes = false;
                    break;
                }
            }
            if (! $todosPresentes) {
                continue;
            }

            $suma = array_sum(array_map(
                fn ($tid) => $selecciones[$indicePorTaller[$tid]]['unitPrice'],
                $tallerIdsCombo
            ));

            $candidatos[] = [
                'combo' => $combo,
                'tallerIds' => $tallerIdsCombo,
                'suma' => $suma,
                'ahorro' => $suma - $precioDelCombo($combo),
            ];
        }

        // Mayor ahorro primero; empate resuelto por id de combo (determinístico).
        usort($candidatos, function ($a, $b) {
            return $b['ahorro'] <=> $a['ahorro'] ?: $a['combo']->id <=> $b['combo']->id;
        });

        $consumidos = [];
        foreach ($candidatos as $c) {
            if (array_intersect($c['tallerIds'], $consumidos)) {
                continue; // algún taller miembro ya quedó en otro combo aceptado antes
            }

            $descuentoTotal = round(max(0.0, $c['ahorro']), 2);
            $indices = array_map(fn ($tid) => $indicePorTaller[$tid], $c['tallerIds']);
            $ultimo = count($indices) - 1;
            $acumulado = 0.0;

            foreach ($indices as $pos => $idx) {
                $unit = $selecciones[$idx]['unitPrice'];

                if ($pos === $ultimo) {
                    $discount = round($descuentoTotal - $acumulado, 2);
                } elseif ($c['suma'] > 0) {
                    $discount = round($descuentoTotal * $unit / $c['suma'], 2);
                } else {
                    $discount = 0.0;
                }
                $acumulado += $discount;

                $resultado[$idx]['discount'] = $discount;
                $resultado[$idx]['total'] = round($unit - $discount, 2);
                $resultado[$idx]['tallerComboId'] = $c['combo']->id;
            }

            $consumidos = array_merge($consumidos, $c['tallerIds']);
        }

        return $resultado;
    }
}
