<?php

namespace App\Http\Resources;

use App\Support\DisponibilidadItemData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemStockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'souvenir_id'    => $this->souvenir_id,
            'talla'          => $this->talla,
            'sexo'           => $this->sexo,
            'cantidad_total' => $this->cantidad_total,
            'disponible'     => DisponibilidadItemData::disponibleParaCombinacion(
                $this->souvenir,
                $this->talla,
                $this->sexo
            ),
        ];
    }
}
