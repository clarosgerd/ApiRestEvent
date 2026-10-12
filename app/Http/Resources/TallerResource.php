<?php

namespace App\Http\Resources;

use App\Support\Taller\PrecioVigenteTallerData;
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
        // Precios por período (12/10/2026) — igual criterio que
        // CategoryResource::precio_vigente: `precio` (campo crudo) sigue
        // siendo el valor guardado en la columna; `precioVigente` es el que
        // debe usar el proxy público para revalidar (ver
        // PrecioVigenteTallerData). Requiere `pricePeriods` eager-cargado
        // para no hacer N+1 en listados — si no está cargado, Eloquent lo
        // resuelve con una query extra (mismo comportamiento que
        // CategoryResource).
        $vigente = PrecioVigenteTallerData::paraTaller($this->resource);

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
            // esPrecongreso / formato (28/09/2026) — ver Taller model.
            'esPrecongreso' => (bool) $this->es_precongreso,
            'formato'       => $this->formato,
            'precioVigente'            => $vigente['precio'],
            'precioUsdVigente'         => $vigente['precio_usd'],
            'periodoVigenteNombre'     => $vigente['periodo_nombre'],
            'periodoVigenteFechaHasta' => $vigente['periodo_fecha_hasta'],
            'periodos'                 => $vigente['periodos'],
            'sesiones'    => TallerSesionResource::collection(
                $this->whenLoaded('sesiones')
            ),
        ];
    }
}