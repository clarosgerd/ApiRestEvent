<?php

namespace App\Support\Taller;

use App\Models\Evento;
use App\Models\TallerCombo;

/**
 * Matching y reparto de combos de talleres (12/10/2026, pedido del
 * usuario) — ej. "Taller A + Taller B por el precio de 1" o "por $300
 * fijo". Recibe SOLO selecciones NUEVAS de un mismo participante, ya
 * resueltas en `unit_price` por ResolverPrecioTallerData — nunca
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
 *   la de menor `unit_price` participa del matching de combo; la otra
 *   queda siempre a precio normal fuera de cualquier combo.
 * - Si varios combos son aplicables a la vez, se aceptan en orden de mayor
 *   ahorro total primero (`suma de unit_price de sus talleres -
 *   precio_combo`), sin reutilizar un taller ya "consumido" por un combo
 *   ya aceptado en esta misma resolución (greedy).
 * - El descuento de un combo aceptado se reparte proporcional al
 *   `unit_price` de cada taller miembro, con el resto de redondeo
 *   absorbido por el último ítem para que la suma cierre exacta.
 */
class ResolverCombosTallerAction
{
    /**
     * @param  array<int, array{sesionId:int, tallerId:int, unitPrice:float}>  $selecciones
     * @return array<int, array{sesionId:int, tallerId:int, unitPrice:float, discount:float, total:float, tallerComboId:?int}>
     */
    public static function resolver(array $selecciones, Evento $evento): array
    {
        $resultado = [];
        foreach ($selecciones as $i => $s) {
            $resultado[$i] = $s + [
                'discount' => 0.0,
                'total' => round($s['unitPrice'], 2),
                'tallerComboId' => null,
            ];
        }

        $combos = TallerCombo::where('evento_id', $evento->id)->with('talleres')->get()
            ->filter(fn (TallerCombo $c) => $c->vigenteHoy())
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
                'ahorro' => $suma - (float) $combo->precio_combo,
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
