<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCiudadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pais_id' => 'sometimes|required|integer|exists:paises,id',
            'nombre' => 'sometimes|required|string|max:100',
            'activo' => 'nullable|boolean',
        ];
    }
}
