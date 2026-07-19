<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class FormTypeResource extends JsonResource
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
           
            'name'            =>$this->name ,
            'icon'            =>$this->icon,
            'description'     =>$this->description,
            'tipo'            =>$this->tipo,
            'cupo_total'      =>$this->cupo_total,
            'precio_base'     =>$this->precio_base,
            'color'           =>$this->color,
            'moneda'          =>$this->moneda,
            'permite_lista_espera'  =>$this->permite_lista_espera,
            'hasshirt'              =>$this->hasshirt,
             'hasQuestion'              =>$this->hasQuestion,
            'requiere_talla'        =>$this->requiere_talla,
            //'souvenirs'             =>new SouvenirResource($this->form_types_id),  // Souvenirs del evento
            //'souvenirs' => SouvenirResource::make($this->whenLoaded('souvenirs')),
       
            'souvenirs' =>SouvenirResource::collection($this->whenLoaded('souvenirs')),
            'preguntas' =>FormularioCamposResource::collection($this->whenLoaded('formularioCampos')),
   
    ];
    }
}
