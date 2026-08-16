<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubtipoEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_evento_id' => 'required|integer|exists:tipos_evento,id',
            'nombre' => 'required|string|max:80',
            'activo' => 'nullable|boolean',
        ];
    }
}
