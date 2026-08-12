<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePresupuestoEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'presupuesto_categoria_id' => 'sometimes|required|integer|exists:presupuesto_categorias,id',
            'tipo' => 'sometimes|required|string|in:ingreso,gasto',
            'monto' => 'sometimes|required|numeric|min:0.01',
            'moneda' => 'nullable|string|max:10',
            'fecha' => 'sometimes|required|date',
            'comprobante_url' => 'nullable|url|max:500',
        ];
    }
}
