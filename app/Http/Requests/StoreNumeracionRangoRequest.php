<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Aviso de numeración vs. género/edad real en entrega de kit (16/09/2026).
 * El chequeo de solapamiento entre rangos queda explícitamente fuera de
 * esta tanda (ver plan) — no se valida acá.
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
            'numero_min' => 'required|integer|min:0',
            'numero_max' => 'required|integer|min:0|gte:numero_min',
        ];
    }
}
