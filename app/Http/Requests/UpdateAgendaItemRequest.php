<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAgendaItemRequest extends FormRequest
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
            'form_type_id'  => 'nullable|integer|exists:form_types,id',
            'fecha'         => 'nullable|date',
            'hora_inicio'   => 'sometimes|string',
            'hora_fin'      => 'nullable|string',
            'titulo'        => 'sometimes|string|max:255',
            'descripcion'   => 'nullable|string',
            'ponente'       => 'nullable|string|max:255',
            'ponente_cargo' => 'nullable|string|max:255',
            'sala'          => 'nullable|string|max:255',
            'icono'         => 'nullable|string|max:10',
            'orden'         => 'nullable|integer',
        ];
    }
}
