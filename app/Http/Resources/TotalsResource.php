<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TotalsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [

            'inscripcion' => (float)$this->inscripcion,
            'donacion' => (float)$this->donacion,
            'souvenirs' => (float)$this->souvenirs,
            'fee' => (float)$this->fee,
            'descuento' => (float)$this->descuento,
            'descuento_registrante' => (float)$this->descuento_registrante,
            'grand_total' => (float)$this->grand_total

        ];
    }
}
