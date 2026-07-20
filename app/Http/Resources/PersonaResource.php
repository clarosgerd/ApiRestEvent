<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
class PersonaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       return [

            'id' => $this->id,
            'email' => $this->email,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'alias' => $this->alias,
            'sexo' => $this->sexo,
            'tipoDocumento' => $this->tipo_documento,
            'numeroDocumento' => $this->numero_documento,
            'nacimiento'=>[
            'age' => Carbon::parse($this->fecha_nacimiento)->age,
            'anio' => Carbon::parse($this->fecha_nacimiento)->year,
            'mes' => Carbon::parse($this->fecha_nacimiento)->month,
            'dia' => Carbon::parse($this->fecha_nacimiento)->day,
            ],
            'correo' => $this->correo,
            'direccion' => $this->direccion,
            'ciudad' => $this->ciudad,
            'telefono' => $this->telefono,
            'celular' => $this->celular,
            'contacto_emergencia' => [
                'nombre' => optional($this->contactoEmergencia)->nombre,
                'celular' => optional($this->contactoEmergencia)->celular,
                'relacion' => optional($this->contactoEmergencia)->relacion,
            ]
        ];
    }
}
