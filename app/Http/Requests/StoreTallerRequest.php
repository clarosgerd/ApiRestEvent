<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación al crear un taller de congreso. Ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md §1.4. La
 * pertenencia del taller al evento se enforce en el controller (no
 * acá) porque `evento_id` se inyecta desde la ruta.
 */
class StoreTallerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'      => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:2000',
            'modalidad'   => 'required|in:REQUIRED,OPTIONAL',
            'precio'      => 'nullable|numeric|min:0',
            // price_usd (19/08/2026) — ver ResolverPrecioTallerData::unitPriceUsd().
            'price_usd'   => 'nullable|numeric|min:0',
            'orden'       => 'nullable|integer|min:0',
            'activo'      => 'nullable|boolean',
            'permite_inscripcion' => 'nullable|boolean',
        ];
    }
}