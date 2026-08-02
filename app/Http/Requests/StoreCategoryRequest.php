<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_id'        => 'required|integer|exists:eventos,id',
            'name'            => 'required|string|max:255',
            'price'           => 'required|numeric|min:0',
            'description'     => 'nullable|string',
            'color'           => 'nullable|string|max:7',
            'formulario_id'   => 'nullable|integer',
            'sexo_id'         => 'nullable|integer',
            'edad_min'        => 'nullable|integer|min:0',
            'edad_max'        => 'nullable|integer|min:0',
            'calculo_edad_id' => 'nullable|integer',
        ];
    }
}
