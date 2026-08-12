<?php

namespace App\Support;

use App\Models\ItemStock;
use App\Models\Souvenir;
use App\Models\SouvenirParticipante;

/**
 * Cálculo de disponibilidad de un ítem del kit (fila de `souvenirs`,
 * ver decisión de terminología en
 * PRD-kit-tallas-stock-lista-espera.md) por talla×sexo. Contando en
 * vivo, no decrementando un contador — mismo criterio que
 * `RegistrationService::deactivateFormTypeIfCupoLleno()` usa para
 * `cupo_total`.
 *
 * Un souvenir sin ninguna fila en `item_stock` tiene "disponibilidad no
 * controlada": no se sabe (ni importa) cuánto queda, se comporta como
 * antes de este feature (siempre disponible). Eso es lo que hace que
 * los eventos existentes (todos migrados a `incluido=true` sin stock —
 * ver 2026_08_11_140400_migrate_polera_incluida_a_souvenirs.php) no
 * cambien de comportamiento hasta que el organizador cargue stock real.
 */
class DisponibilidadItemData
{
    /**
     * Filas de disponibilidad por talla×sexo. Array vacío = sin stock
     * controlado (disponibilidad no controlada), no "agotado".
     *
     * @return array<int, array{talla: ?string, sexo: ?string, cantidad_total: int, disponible: int}>
     */
    public static function paraSouvenir(Souvenir $souvenir): array
    {
        $stocks = $souvenir->stock()->get();

        return $stocks->map(function (ItemStock $stock) use ($souvenir) {
            return [
                'talla'          => $stock->talla,
                'sexo'           => $stock->sexo,
                'cantidad_total' => $stock->cantidad_total,
                'disponible'     => max(0, $stock->cantidad_total - self::ocupado($souvenir->id, $stock->talla, $stock->sexo)),
            ];
        })->values()->all();
    }

    /**
     * Disponible para una combinación puntual. `null` = disponibilidad
     * no controlada (no hay fila de stock para esa combinación) — el
     * llamador decide qué hacer con eso (normalmente: no bloquear).
     */
    public static function disponibleParaCombinacion(Souvenir $souvenir, ?string $talla, ?string $sexo): ?int
    {
        $stock = ItemStock::where('souvenir_id', $souvenir->id)
            ->where('talla', $talla)
            ->where('sexo', $sexo)
            ->first();

        if (! $stock) {
            return null;
        }

        return max(0, $stock->cantidad_total - self::ocupado($souvenir->id, $talla, $sexo));
    }

    /**
     * Cuántas unidades de esa combinación están consumidas por
     * inscripciones vigentes (ni canceladas ni fallidas) — mismo filtro
     * que usa el cupo del form_type.
     */
    private static function ocupado(int $souvenirId, ?string $talla, ?string $sexo): int
    {
        return SouvenirParticipante::where('souvenir_id', $souvenirId)
            ->where('talla', $talla)
            ->where('sexo', $sexo)
            ->whereHas('participante.registration', function ($q) {
                $q->whereNotIn('pago_status', ['cancelled', 'failed']);
            })
            ->count();
    }
}
