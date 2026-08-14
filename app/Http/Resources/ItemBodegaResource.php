<?php

namespace App\Http\Resources;

use App\Support\DisponibilidadItemData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bodega de stock por evento — ver PLAN-BODEGA-STOCK-EVENTO-14082026.md.
 * `asignaciones` es el resumen agregado: cada `Souvenir` vinculado a este
 * ítem de bodega (uno por form_type que lo ofrece), con su cupo/
 * disponible propio (cupos separados por form_type, no un pool
 * compartido).
 */
class ItemBodegaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'evento_id'       => $this->evento_id,
            'nombre'          => $this->nombre,
            'icon'            => $this->icon,
            'foto_url'        => $this->foto_url,
            'requiere_talla'  => (bool) $this->requiere_talla,
            'requiere_sexo'   => (bool) $this->requiere_sexo,
            'asignaciones'    => $this->whenLoaded('asignaciones', function () {
                return $this->asignaciones->map(function ($souvenir) {
                    $tallas = DisponibilidadItemData::paraSouvenir($souvenir);

                    return [
                        'souvenir_id'     => $souvenir->id,
                        'form_types_id'   => $souvenir->form_types_id,
                        'form_type_nombre' => $souvenir->formType->name ?? null,
                        'price'           => $souvenir->price,
                        'incluido'        => (bool) $souvenir->incluido,
                        // Sin filas de stock cargadas = disponibilidad no
                        // controlada, no "0" — se distingue con null para
                        // que el resumen no lo muestre como agotado.
                        'cupo_total'      => $tallas ? array_sum(array_column($tallas, 'cantidad_total')) : null,
                        'disponible'      => $tallas ? array_sum(array_column($tallas, 'disponible')) : null,
                    ];
                })->values();
            }),
        ];
    }
}
