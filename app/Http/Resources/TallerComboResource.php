<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Combo de talleres (12/10/2026, pedido del usuario) — ver
 * App\Models\TallerCombo / App\Support\Taller\ResolverCombosTallerAction.
 * Requiere `talleres`/`precios.category` eager-cargados para no hacer N+1.
 *
 * El precio ya NO es un campo plano (13/10/2026) — el mismo combo puede
 * costar distinto según la categoría, ver `precios`.
 */
class TallerComboResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'eventoId'   => $this->evento_id,
            'nombre'     => $this->nombre,
            'fechaDesde' => optional($this->fecha_desde)->toDateString(),
            'fechaHasta' => optional($this->fecha_hasta)->toDateString(),
            'activo'     => (bool) $this->activo,
            'vigente'    => $this->resource->vigenteHoy(),
            'tallerIds'  => $this->whenLoaded('talleres', fn () => $this->talleres->pluck('id')->all()),
            // precios: un ítem por categoría con precio cargado;
            // categoriaId/categoriaNombre null = fila default (aplica a
            // cualquier categoría sin fila propia).
            'precios'    => $this->whenLoaded('precios', fn () => $this->precios->map(fn ($p) => [
                'id'              => $p->id,
                'categoriaId'     => $p->category_id,
                'categoriaNombre' => $p->category?->name,
                'precio'          => (float) $p->precio,
            ])->values()),
        ];
    }
}
