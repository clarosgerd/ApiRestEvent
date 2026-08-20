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
            // Congresos con talleres (18/08/2026) — null = ponencia suelta
            // (no seleccionable en inscripción). El taller debe ser del
            // mismo evento (validación adicional vive en el controller).
            'taller_id' => 'nullable|integer|exists:talleres,id',
            'titulo' => 'required|string|max:255',
            'ponente' => 'nullable|string|max:255',
            'ponente_cargo' => 'nullable|string|max:255',
            'sala' => 'nullable|string|max:255',
            'fecha' => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
            'cupo' => 'nullable|integer|min:1',
            // Override de precio a nivel de sesión — si es NULL hereda el
            // precio del taller. Solo aplica si evento.talleres_con_costo.
            'precio' => 'nullable|numeric|min:0',
            // price_usd (19/08/2026) — mismo override, para el modo Precio
            // USD fijo. Ver ResolverPrecioTallerData::unitPriceUsd().
            'price_usd' => 'nullable|numeric|min:0',
            'requiere_inscripcion' => 'nullable|boolean',
            'activa' => 'nullable|boolean',
        ];
    }
}
