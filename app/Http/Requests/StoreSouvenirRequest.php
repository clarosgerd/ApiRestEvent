<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSouvenirRequest extends FormRequest
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
            'form_types_id'   => 'required|integer|exists:form_types,id',
            'name'            => 'required|string|max:255',
            'icon'            => 'nullable|string|max:10',
            'price'           => 'required|numeric|min:0',
            // Kit/tallas/stock (11/08/2026) — ver
            // PRD-kit-tallas-stock-lista-espera.md.
            'incluido'        => 'nullable|boolean',
            'foto_url'        => 'nullable|string|max:2048|url',
            'requiere_talla'  => 'nullable|boolean',
            'requiere_sexo'   => 'nullable|boolean',
        ];
    }
}
