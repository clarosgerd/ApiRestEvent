<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgendaItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'formTypeId'   => $this->form_type_id,
            'date'         => $this->fecha,
            'startTime'    => $this->hora_inicio,
            'endTime'      => $this->hora_fin,
            'title'        => $this->titulo,
            'description'  => $this->descripcion,
            'speaker'      => $this->ponente,
            'speakerRole'  => $this->ponente_cargo,
            'room'         => $this->sala,
            'icon'         => $this->icono,
            'orden'        => $this->orden,
        ];
    }
}
