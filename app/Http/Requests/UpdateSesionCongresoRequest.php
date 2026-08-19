<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSesionCongresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agenda_item_id' => 'nullable|integer|exists:agenda_items,id',
            // Congresos con talleres (18/08/2026) — ver Store. Se permite
            // `null` para "desvincular la sesión del taller".
            'taller_id' => 'nullable|integer|exists:talleres,id',
            'titulo' => 'sometimes|required|string|max:255',
            'ponente' => 'nullable|string|max:255',
            'ponente_cargo' => 'nullable|string|max:255',
            'sala' => 'nullable|string|max:255',
            'fecha' => 'sometimes|required|date',
            'hora_inicio' => 'sometimes|required|date_format:H:i',
            'hora_fin' => 'sometimes|required|date_format:H:i|after:hora_inicio',
            'cupo' => 'nullable|integer|min:1',
            'precio' => 'nullable|numeric|min:0',
            'requiere_inscripcion' => 'nullable|boolean',
            'activa' => 'nullable|boolean',
        ];
    }
}
