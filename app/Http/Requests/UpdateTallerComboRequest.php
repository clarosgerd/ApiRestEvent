<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Combos de talleres (12/10/2026) — mismas reglas de forma que Store.
 */
class UpdateTallerComboRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'       => 'required|string|max:255',
            'fecha_desde'  => 'nullable|date',
            'fecha_hasta'  => ['nullable', 'date', Rule::when(
                fn () => $this->filled('fecha_desde'),
                ['after_or_equal:fecha_desde']
            )],
            'activo'       => 'nullable|boolean',
            'taller_ids'   => 'required|array|min:2',
            'taller_ids.*' => 'required|integer|exists:talleres,id',
        ];
    }
}
