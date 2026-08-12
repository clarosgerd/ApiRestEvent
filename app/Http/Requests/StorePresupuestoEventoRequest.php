<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePresupuestoEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'presupuesto_categoria_id' => 'required|integer|exists:presupuesto_categorias,id',
            'tipo' => 'required|string|in:ingreso,gasto',
            'monto' => 'required|numeric|min:0.01',
            'moneda' => 'nullable|string|max:10',
            'fecha' => 'required|date',
            'comprobante_url' => 'nullable|url|max:500',
        ];
    }
}
