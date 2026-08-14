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

    /**
     * Agrega un listado de selecciones (una fila por participante×ítem
     * elegido) en un mapa por combinación souvenir_id+talla+sexo con la
     * cantidad pedida — mismo agrupamiento que antes vivía inline en
     * CrearInscripcionAction::validateFormTypeActivoYStock(), extraído
     * acá (13/08/2026, ver PLAN-STOCK-SOUVENIRS-SIMPLES-13082026.md) para
     * poder reusarlo también al editar una inscripción existente.
     *
     * @param iterable<array{souvenir_id:int, talla:?string, sexo:?string}> $selecciones
     * @return array<string, array{souvenir_id:int, talla:?string, sexo:?string, cantidad:int}>
     */
    public static function agregarDemanda(iterable $selecciones): array
    {
        $demanda = [];

        foreach ($selecciones as $seleccion) {
            $souvenirId = (int) $seleccion['souvenir_id'];
            $talla = $seleccion['talla'] ?? null;
            $sexo = $seleccion['sexo'] ?? null;
            $key = $souvenirId . '|' . ($talla ?? '') . '|' . ($sexo ?? '');

            if (!isset($demanda[$key])) {
                $demanda[$key] = [
                    'souvenir_id' => $souvenirId,
                    'talla' => $talla,
                    'sexo' => $sexo,
                    'cantidad' => 0,
                ];
            }

            $demanda[$key]['cantidad']++;
        }

        return $demanda;
    }

    /**
     * Valida una demanda agregada (ver agregarDemanda()) contra el stock
     * cargado y lanza DomainException si alguna combinación no alcanza —
     * mismo mensaje que ya usaban CrearInscripcionAction y el
     * pre-chequeo de elascenso/event. Con lockForUpdate() en ambas
     * consultas (mismo criterio que el resto del feature de stock) para
     * que dos requests concurrentes contra la última unidad no pasen las
     * dos — debe llamarse dentro de una transacción.
     *
     * Ítems sin ninguna fila en `item_stock` (disponibilidad no
     * controlada) no se bloquean, mismo comportamiento de siempre.
     *
     * Reusado (13/08/2026) por CrearInscripcionAction (alta) y
     * RegistrationService::validateStockForParticipants() (edición) — ver
     * PLAN-STOCK-SOUVENIRS-SIMPLES-13082026.md, punto 2: antes solo se
     * revalidaba al crear, nunca al editar una inscripción existente.
     *
     * @param array<string, array{souvenir_id:int, talla:?string, sexo:?string, cantidad:int}> $demanda
     */
    public static function validarDemandaOFail(array $demanda): void
    {
        foreach ($demanda as $item) {
            $stock = ItemStock::where('souvenir_id', $item['souvenir_id'])
                ->where('talla', $item['talla'])
                ->where('sexo', $item['sexo'])
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                continue; // disponibilidad no controlada, no se bloquea
            }

            $ocupado = SouvenirParticipante::where('souvenir_id', $item['souvenir_id'])
                ->where('talla', $item['talla'])
                ->where('sexo', $item['sexo'])
                ->whereHas('participante.registration', function ($q) {
                    $q->whereNotIn('pago_status', ['cancelled', 'failed']);
                })
                ->lockForUpdate()
                ->count();

            if ($ocupado + $item['cantidad'] > $stock->cantidad_total) {
                $souvenir = Souvenir::find($item['souvenir_id']);
                throw new \DomainException(
                    sprintf(
                        'No hay stock suficiente de "%s"%s. Quedan %d disponibles.',
                        $souvenir->name ?? 'el ítem seleccionado',
                        $item['talla'] ? " (talla {$item['talla']})" : '',
                        max(0, $stock->cantidad_total - $ocupado)
                    )
                );
            }
        }
    }
}
