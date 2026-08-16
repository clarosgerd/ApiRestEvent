<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'sometimes|required|string|max:100',
            'iso2' => 'sometimes|required|string|size:2',
            'iso3' => 'nullable|string|size:3',
            'prefijo_tel' => 'nullable|string|max:6',
            'bandera_url' => 'nullable|string|max:255',
            'activo' => 'nullable|boolean',
        ];
    }
}
