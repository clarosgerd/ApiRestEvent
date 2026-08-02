<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRouteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `label` es NOT NULL en la tabla `routes` sin default — a diferencia
     * de la regla `nullable` que usa StoreEventosRequest para el `route`
     * anidado (gap preexistente ahí, no se toca), acá se valida como
     * requerido para evitar un error de SQL.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_id' => 'required|integer|exists:eventos,id',
            'lat'      => 'required|numeric',
            'lng'      => 'required|numeric',
            'label'    => 'required|string|max:500',
        ];
    }
}
