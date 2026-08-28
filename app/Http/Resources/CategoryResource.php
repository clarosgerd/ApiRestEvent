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
            // Categorías por form_type (27/08/2026) — ver
            // PLAN-CATEGORIAS-POR-FORM-TYPE-27082026.md. Se mantiene el
            // nombre de columna (snake_case), igual que `form_types_id` en
            // SouvenirResource/RegistrationResource/etc. Null = categoría
            // compartida por todos los form_types del evento
            // (comportamiento actual, sin cambios para quien no la use).
            'formulario_id'               => $this->formulario_id,
            'price'                       => $this->price,
            // Precio USD fijo, sin tipo de cambio (19/08/2026) — ver
            // brain/PLAN-PRECIO-USD-FIJO-19082026.md. Null si el
            // organizador no cargó precio en USD para esta categoría (no
            // vendible en USD fijo hasta que lo cargue).
            'priceUsd'                    => $this->price_usd,
            'description'                 => $this->description,
            'color'                       => $this->color,
            'precio_vigente'              => $vigente['precio'],
            // Precio USD fijo por período (20/08/2026) — mismo criterio
            // que precio_vigente, ver PrecioVigenteData. El frontend de
            // inscripción (usdPrecioFijo) y CurrencyResolverData::
            // resolverPrecioFijo() deben usar este campo, no `priceUsd`
            // directo (ese es el valor plano de la categoría, sin
            // resolver por período).
            'precio_usd_vigente'          => $vigente['precio_usd'],
            'periodo_vigente_nombre'      => $vigente['periodo_nombre'],
            'periodo_vigente_fecha_hasta' => $vigente['periodo_fecha_hasta'],
            'periodos'                    => $vigente['periodos'],
        ];
    }
}
