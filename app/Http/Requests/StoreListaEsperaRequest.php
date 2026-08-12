<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Kit/tallas/stock (11/08/2026) — ver
 * PRD-kit-tallas-stock-lista-espera.md. Público, sin auth (mismo
 * criterio que /registrations/lookup) — quien se anota no
 * necesariamente tiene cuenta todavía.
 */
class StoreListaEsperaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'form_types_id' => 'required|integer|exists:form_types,id',
            'souvenir_id'   => 'nullable|integer|exists:souvenirs,id',
            'talla'         => 'nullable|string|max:20',
            'sexo'          => 'nullable|in:masculino,femenino,unisex',
            'nombre'        => 'required|string|max:255',
            'correo'        => 'required|email|max:255',
            'telefono'      => 'nullable|string|max:50',
        ];
    }
}
