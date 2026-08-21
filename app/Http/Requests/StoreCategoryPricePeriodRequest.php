<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Precios por período (12/08/2026) — ver PRD-precios-periodos-fechas.md.
 * El chequeo de "sin traslapes con otro período de la misma categoría"
 * no va acá (depende de las filas ya guardadas para esa categoría, no
 * es una regla de forma) — vive en el controller, mismo criterio que la
 * unicidad de talla/sexo en StoreItemStockRequest/ItemStockController.
 */
class StoreCategoryPricePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'      => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            // Precio USD fijo por período (20/08/2026) — opcional, ver
            // PrecioVigenteData::precioUsdDelPeriodo().
            'price_usd'   => 'nullable|numeric|min:0',
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
        ];
    }
}
