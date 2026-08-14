<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bodega de stock por evento — ver PLAN-BODEGA-STOCK-EVENTO-14082026.md.
 */
class UpdateItemBodegaRequest extends FormRequest
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
            'nombre'         => 'required|string|max:255',
            'icon'           => 'nullable|string|max:10',
            'foto_url'       => 'nullable|string|max:2048|url',
            'requiere_talla' => 'nullable|boolean',
            'requiere_sexo'  => 'nullable|boolean',
        ];
    }
}
