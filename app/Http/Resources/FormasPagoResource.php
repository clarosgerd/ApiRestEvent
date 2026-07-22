<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormasPagoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'tipo' => $this->tipo,
            'pasarela' => $this->pasarela,
            // config solo se expone para métodos manuales (instrucciones,
            // cuenta bancaria, etc.). Para los integrados nunca debe viajar
            // nada por acá — las credenciales viven solo en el backend de
            // cada integración.
            'config' => $this->tipo === 'manual' ? $this->config : null,
        ];
    }
}
