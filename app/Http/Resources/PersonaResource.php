<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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

            'email' => $this->email,
            // si deseas enviar password
            'password' => $this->password,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'alias' => $this->alias,
            'sexo' => $this->sexo,
            'tipoDocumento' => $this->tipo_documento,
            'numeroDocumento' => $this->numero_documento,
            'dia' => optional($this->fecha_nacimiento)->format('d'),
            'mes' => optional($this->fecha_nacimiento)->format('m'),
            'anio' => optional($this->fecha_nacimiento)->format('Y'),
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
