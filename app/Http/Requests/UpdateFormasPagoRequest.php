<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ver StoreFormasPagoRequest — mismo criterio, `slug` único ignorando la
 * propia fila.
 */
class UpdateFormasPagoRequest extends FormRequest
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
            'slug' => [
                'sometimes', 'string', 'max:50', 'alpha_dash',
                Rule::unique('formas_pagos', 'slug')->ignore($this->route('formasPago')),
            ],
            'nombre' => 'sometimes|string|max:100',
            'descripcion' => 'nullable|string|max:255',
            'pasarela' => 'nullable|string|max:50',
            'tipo' => ['sometimes', Rule::in(['integrado', 'manual'])],
            'config' => 'nullable|array',
            'activo' => 'nullable|boolean',
        ];
    }
}
