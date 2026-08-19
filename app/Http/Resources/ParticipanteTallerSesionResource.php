<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila persistida del pivote `participante_taller_sesion` que se expone
 * en `ParticipanteResource.talleres[]` cuando el participante carga la
 * inscripción. Se aplana con datos de la sesión para que el frontend
 * pueda renderizar el resumen sin un join extra. Ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
 */
class ParticipanteTallerSesionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sesion = $this->sesionCongreso;
        $taller = $this->taller;

        return [
            'sesionCongresoId' => $this->sesion_congreso_id,
            'tallerId'         => $this->taller_id,
            'tallerNombre'     => $taller?->nombre,
            'modalidad'        => $taller?->modalidad,
            'titulo'           => $sesion?->titulo,
            'fecha'            => optional($sesion?->fecha)->format('Y-m-d'),
            'horaInicio'       => $sesion ? substr((string) $sesion->hora_inicio, 0, 5) : null,
            'horaFin'          => $sesion ? substr((string) $sesion->hora_fin, 0, 5) : null,
            'sala'             => $sesion?->sala,
            'unitPrice'        => (float) $this->unit_price,
            'discount'         => (float) $this->discount,
            'total'            => (float) $this->total,
        ];
    }
}