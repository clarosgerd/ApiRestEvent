<?php

namespace App\Http\Resources;

use App\Support\PrecioVigenteData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Precios por período (12/08/2026) — ver
        // PRD-precios-periodos-fechas.md. `price` (el campo crudo) NO
        // cambia de significado — sigue siendo el valor guardado en la
        // columna, a propósito: el formulario de edición de categoría en
        // admin-eventos lee `price` para precargar el input, y si ese
        // campo pasara a ser el precio computado de hoy, guardar sin
        // querer sobrescribiría el precio base legado con el precio de
        // hoy. `precio_vigente` es el campo nuevo que el frontend de
        // inscripción y admin-eventos deben usar para mostrar/cobrar.
        // Requiere `pricePeriods` eager-cargado por el controller para no
        // hacer N+1 en listados.
        $vigente = PrecioVigenteData::paraCategoria($this->resource);

        return [
            'id'                          => $this->id,
            'name'                        => $this->name,
            'price'                       => $this->price,
            'description'                 => $this->description,
            'color'                       => $this->color,
            'precio_vigente'              => $vigente['precio'],
            'periodo_vigente_nombre'      => $vigente['periodo_nombre'],
            'periodo_vigente_fecha_hasta' => $vigente['periodo_fecha_hasta'],
            'periodos'                    => $vigente['periodos'],
        ];
    }
}
