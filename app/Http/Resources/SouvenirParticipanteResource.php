<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SouvenirParticipanteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
         return [
            'participante_id' => $this->participante_id,
            'nombre' => $this->nombre,
            'precio' => (float)$this->precio
   
    ];
    }
}
