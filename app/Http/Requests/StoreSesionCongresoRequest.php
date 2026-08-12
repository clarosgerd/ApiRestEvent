<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSesionCongresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agenda_item_id' => 'nullable|integer|exists:agenda_items,id',
            'titulo' => 'required|string|max:255',
            'ponente' => 'nullable|string|max:255',
            'ponente_cargo' => 'nullable|string|max:255',
            'sala' => 'nullable|string|max:255',
            'fecha' => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
            'cupo' => 'nullable|integer|min:1',
            'requiere_inscripcion' => 'nullable|boolean',
            'activa' => 'nullable|boolean',
        ];
    }
}
