<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubtipoEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_evento_id' => 'sometimes|required|integer|exists:tipos_evento,id',
            'nombre' => 'sometimes|required|string|max:80',
            'activo' => 'nullable|boolean',
        ];
    }
}
