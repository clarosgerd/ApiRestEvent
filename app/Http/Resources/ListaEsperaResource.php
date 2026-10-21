<?php

namespace App\Http\Resources;

use App\Support\FechaReporteData;
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
            // Hora real de Bolivia (21/10/2026) — ambos son UTC de verdad
            // (PromoverListaEsperaAction::handle() usa now()); sin
            // convertir, se mostraban 4h adelantados.
            'promovido_at'  => FechaReporteData::iso($this->promovido_at),
            'created_at'    => FechaReporteData::iso($this->created_at),
        ];
    }
}
