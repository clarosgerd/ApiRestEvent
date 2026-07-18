<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ContactoEmergenciaParticipanteResource;
use App\Http\Resources\SouvenirParticipanteResource;
use App\Http\Resources\AnswerResource;

class ParticipanteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
             'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'alias' => $this->alias,
            'genero' => $this->genero,
            'tipoDocumento' => $this->tipo_documento,
            'numeroDocumento' => $this->numero_documento,
            'polera' => $this->polera,
            'precioPolera' => (float)$this->precio_polera,
            'nacimiento' => [
                'dia' => optional($this->fecha_nacimiento)->format('d'),
                'mes' => optional($this->fecha_nacimiento)->format('m'),
                'anio' => optional($this->fecha_nacimiento)->format('Y')

            ],

            'edad' => $this->edad,
            'correo' => $this->correo,
            'direccion' => $this->direccion,
            'ciudad' => $this->ciudad,
            'telefono' => $this->telefono,

            'contacto_emergencia' => new ContactoEmergenciaParticipanteResource(
                $this->whenLoaded('contactoEmergenciaParticipante')
            ),

            'souvenirs' => SouvenirParticipanteResource::collection(
                $this->whenLoaded('souvenirParticipante')
            ),

            'answers' => AnswerResource::collection(
                $this->whenLoaded('answers')
            ),

            'categoria' => (string)$this->categoria,
            'precioCategoria' => (float)$this->precio_categoria,
            'donacion' => (float)$this->donacion,
            'promoDescuento' => (float)$this->promo_descuento,
            'promoCodigo' => $this->promo_codigo,
            'subtotal' => (float)$this->subtotal
        ];
    }
}
