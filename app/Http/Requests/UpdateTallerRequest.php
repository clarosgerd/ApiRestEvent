<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTallerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'      => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string|max:2000',
            'modalidad'   => 'sometimes|required|in:REQUIRED,OPTIONAL',
            'precio'      => 'nullable|numeric|min:0',
            'orden'       => 'nullable|integer|min:0',
            'activo'      => 'nullable|boolean',
        ];
    }
}