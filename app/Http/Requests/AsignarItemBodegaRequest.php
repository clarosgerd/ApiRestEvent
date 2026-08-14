<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Asignar un ítem de bodega a un form_type — ver
 * PLAN-BODEGA-STOCK-EVENTO-14082026.md. La validación de que
 * `form_types_id` pertenezca al MISMO evento que la bodega vive en el
 * controller (necesita el `ItemBodega` resuelto por route model binding,
 * no solo el id) — acá solo se valida que exista.
 */
class AsignarItemBodegaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'form_types_id' => 'required|integer|exists:form_types,id',
        ];
    }
}
