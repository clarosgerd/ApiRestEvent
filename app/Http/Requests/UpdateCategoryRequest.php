<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
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
            'name'            => 'sometimes|string|max:255',
            'price'           => 'sometimes|numeric|min:0',
            'description'     => 'sometimes|nullable|string',
            'color'           => 'sometimes|nullable|string|max:7',
            'formulario_id'   => 'sometimes|nullable|integer',
            'sexo_id'         => 'sometimes|nullable|integer',
            'edad_min'        => 'sometimes|nullable|integer|min:0',
            'edad_max'        => 'sometimes|nullable|integer|min:0',
            'calculo_edad_id' => 'sometimes|nullable|integer',
        ];
    }
}
