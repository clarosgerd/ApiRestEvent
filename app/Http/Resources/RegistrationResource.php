<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\TotalsResource;
use App\Http\Resources\ParticipanteResource;


class RegistrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       return [

            'referencia' => $this->referencia,
            'fecha' => optional($this->fecha)
                ->format('Y-m-d H:i:s'),
            'evento_id' => (string) $this->evento_id,
            'evento_nombre' => $this->evento_nombre,
            'tipo_pago' => $this->tipo_pago,
            'pago_status' => $this->pago_status,
            'totales' => new TotalsResource(
                $this->whenLoaded('totals')
            ),
            'participantes' => ParticipanteResource::collection(
                $this->whenLoaded('participants')
            )
        ];
    }
}
