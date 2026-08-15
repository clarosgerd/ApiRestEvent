<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CerrarTurnoCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monto_contado' => ['required', 'numeric', 'min:0'],
            'notas'         => ['nullable', 'string', 'max:1000'],
        ];
    }
}
