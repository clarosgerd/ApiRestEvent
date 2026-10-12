<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Combo de talleres (12/10/2026, pedido del usuario) — ver
 * App\Models\TallerCombo / App\Support\Taller\ResolverCombosTallerAction.
 * Requiere `talleres` eager-cargado para no hacer N+1.
 */
class TallerComboResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'eventoId'      => $this->evento_id,
            'nombre'        => $this->nombre,
            'precioCombo'   => (float) $this->precio_combo,
            'priceUsdCombo' => $this->price_usd_combo !== null ? (float) $this->price_usd_combo : null,
            'fechaDesde'    => optional($this->fecha_desde)->toDateString(),
            'fechaHasta'    => optional($this->fecha_hasta)->toDateString(),
            'activo'        => (bool) $this->activo,
            'vigente'       => $this->resource->vigenteHoy(),
            'tallerIds'     => $this->whenLoaded('talleres', fn () => $this->talleres->pluck('id')->all()),
        ];
    }
}
