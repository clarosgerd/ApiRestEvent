<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Aviso de numeración vs. género/edad real en entrega de kit (16/09/2026).
 * El chequeo de solapamiento entre rangos queda explícitamente fuera de
 * esta tanda (ver plan) — no se valida acá.
 *
 * `numero_min`/`numero_max` opcionales (23/09/2026) — a pedido del usuario,
 * para la recategorización visual (ver App\Support\RecategorizacionResolver)
 * alcanza con género+edad, el rango de numeración/color solo hace falta
 * para el aviso de bib. `required_with` fuerza "los dos o ninguno" — no
 * tiene sentido guardar solo uno de los dos.
 */
class StoreNumeracionRangoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'genero_id'  => 'required|integer|exists:generos,id',
            'edad_min'   => 'required|integer|min:0',
            'edad_max'   => 'required|integer|min:0|gte:edad_min',
            'color'      => 'required|string|max:7',
            'numero_min' => 'nullable|required_with:numero_max|integer|min:0',
            'numero_max' => 'nullable|required_with:numero_min|integer|min:0|gte:numero_min',
        ];
    }
}
