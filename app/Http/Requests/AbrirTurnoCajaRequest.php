<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AbrirTurnoCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fondo_inicial' => ['required', 'numeric', 'min:0'],
        ];
    }
}
