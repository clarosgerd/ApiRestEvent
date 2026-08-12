<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSouvenirRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'            => 'sometimes|string|max:255',
            'icon'            => 'sometimes|nullable|string|max:10',
            'price'           => 'sometimes|numeric|min:0',
            // Kit/tallas/stock (11/08/2026) — ver
            // PRD-kit-tallas-stock-lista-espera.md.
            'incluido'        => 'sometimes|boolean',
            'foto_url'        => 'sometimes|nullable|string|max:2048|url',
            'requiere_talla'  => 'sometimes|boolean',
            'requiere_sexo'   => 'sometimes|boolean',
        ];
    }
}
