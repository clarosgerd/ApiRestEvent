<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListaEsperaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'form_types_id' => $this->form_types_id,
            'souvenir_id'   => $this->souvenir_id,
            'souvenir_nombre' => $this->souvenir?->name,
            'talla'         => $this->talla,
            'sexo'          => $this->sexo,
            'nombre'        => $this->nombre,
            'correo'        => $this->correo,
            'telefono'      => $this->telefono,
            'estado'        => $this->estado,
            'promovido_at'  => $this->promovido_at?->toIso8601String(),
            'created_at'    => $this->created_at?->toIso8601String(),
        ];
    }
}
