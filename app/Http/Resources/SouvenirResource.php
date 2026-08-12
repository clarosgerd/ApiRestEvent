<?php

namespace App\Http\Resources;

use App\Support\DisponibilidadItemData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SouvenirResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
      return [
            'id'              =>$this->id,
            'form_types_id'            =>$this->form_types_id ,
            'name'            =>$this->name ,
            'icon'            =>$this->icon,
            'price'           =>$this->price,
            // Kit/tallas/stock (11/08/2026) — ver
            // PRD-kit-tallas-stock-lista-espera.md. "ítem" es la palabra
            // que ve el organizador/participante, el modelo sigue
            // siendo Souvenir (decisión de terminología del PRD).
            'incluido'        => (bool) $this->incluido,
            'foto_url'        => $this->foto_url,
            'requiere_talla'  => (bool) $this->requiere_talla,
            'requiere_sexo'   => (bool) $this->requiere_sexo,
            // Array vacío = disponibilidad no controlada (sin filas de
            // stock cargadas) — no significa "agotado", ver
            // DisponibilidadItemData.
            'tallas'          => DisponibilidadItemData::paraSouvenir($this->resource),

    ];
    }
}
