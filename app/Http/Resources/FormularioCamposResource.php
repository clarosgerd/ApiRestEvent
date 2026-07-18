<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormularioCamposResource extends JsonResource
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
           
            'seccion'            =>$this->seccion ,
            'nombre_campo'            =>$this->nombre_campo,
            'etiqueta'     =>$this->etiqueta,
            'tipo_input'            =>$this->tipo_input,
            'opciones'      =>$this->opciones,
            'placeholder'     =>$this->placeholder,
            'obligatorio'           =>$this->obligatorio,
            'visible_en_reporte'          =>$this->visible_en_reporte,
            'orden'  =>$this->orden,
            'options' =>QuestionOptionsResource::collection($this->whenLoaded('options')),
    ];
    }
}
