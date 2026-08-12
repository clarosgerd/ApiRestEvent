<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Kit/tallas/stock (11/08/2026) — ver
 * PRD-kit-tallas-stock-lista-espera.md. Solo `cantidad_total` es
 * editable — talla/sexo identifican la fila, cambiarlos sería borrar y
 * crear una nueva (evita reasignar en caliente el significado de una
 * combinación que ya tiene participantes con esa talla registrada).
 */
class UpdateItemStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cantidad_total' => 'required|integer|min:0',
        ];
    }
}
