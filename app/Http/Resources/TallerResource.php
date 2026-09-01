<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso público de un taller (congreso). Carga sus sesiones vía la
 * relación `sesiones`. Las sesiones se filtran a activas en el Resource
 * de la sesión (ver `TallerSesionResource`) — no acá, para mantener el
 * Resource simple. Ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
 */
class TallerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'eventoId'    => $this->evento_id,
            'nombre'      => $this->nombre,
            'descripcion' => $this->descripcion,
            'modalidad'   => $this->modalidad,
            'precio'      => $this->precio !== null ? (float) $this->precio : null,
            // precioUsd (19/08/2026) — ver ResolverPrecioTallerData::unitPriceUsd().
            'precioUsd'   => $this->price_usd !== null ? (float) $this->price_usd : null,
            'orden'       => (int) $this->orden,
            'activo'      => (bool) $this->activo,
            // permite_inscripcion (28/08/2026) — ver Taller model. false =
            // sigue visible en la lista, pero no seleccionable.
            'permiteInscripcion' => (bool) $this->permite_inscripcion,
            'sesiones'    => TallerSesionResource::collection(
                $this->whenLoaded('sesiones')
            ),
        ];
    }
}