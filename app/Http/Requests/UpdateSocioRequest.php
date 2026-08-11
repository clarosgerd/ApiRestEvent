<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSocioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'sometimes|required|string|max:255',
            'porcentaje' => 'sometimes|required|numeric|min:0|max:100',
            'activo' => 'nullable|boolean',
        ];
    }
}
