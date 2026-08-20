<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sesión de un taller (es `SesionCongreso` con `taller_id` no nulo). Se
 * expone con campos pensados para que el frontend arme el selector de
 * talleres: `ocupados` y `disponibles` permiten mostrar "X/Y cupos" sin
 * pedir otro endpoint. Ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
 */
class TallerSesionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ocupados = $this->participanteSesiones()->count();

        return [
            'id'         => $this->id,
            'tallerId'   => $this->taller_id,
            'titulo'     => $this->titulo,
            'ponente'    => $this->ponente,
            'sala'       => $this->sala,
            'fecha'      => optional($this->fecha)->format('Y-m-d'),
            'horaInicio' => substr((string) $this->hora_inicio, 0, 5),
            'horaFin'    => substr((string) $this->hora_fin, 0, 5),
            'cupo'       => $this->cupo,
            'ocupados'   => $ocupados,
            'disponibles'=> $this->cupo === null ? null : max(0, (int) $this->cupo - $ocupados),
            'precio'     => $this->precio !== null ? (float) $this->precio : null,
            // precioUsd (19/08/2026) — override de sesión, ver TallerResource.
            'precioUsd'  => $this->price_usd !== null ? (float) $this->price_usd : null,
            'activa'     => (bool) $this->activa,
        ];
    }
}