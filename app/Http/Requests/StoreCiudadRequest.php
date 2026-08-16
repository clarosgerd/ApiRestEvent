<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCiudadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pais_id' => 'required|integer|exists:paises,id',
            'nombre' => 'required|string|max:100',
            'activo' => 'nullable|boolean',
        ];
    }
}
