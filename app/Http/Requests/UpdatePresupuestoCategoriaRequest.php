<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePresupuestoCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'sometimes|required|string|max:255',
            'tipo' => 'sometimes|required|string|in:ingreso,gasto',
            'activo' => 'nullable|boolean',
        ];
    }
}
