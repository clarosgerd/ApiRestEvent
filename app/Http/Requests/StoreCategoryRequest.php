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
     * Precio USD fijo (19/08/2026) — un <input type="number"> vacío en
     * admin-eventos manda `price_usd=""`, no ausente. Sin esto, "sin
     * precio en USD" fallaría la regla `numeric` en vez de guardarse como
     * null (mismo patrón que StoreEventosRequest con organizador_id).
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('price_usd') === '') {
            $this->merge(['price_usd' => null]);
        }
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
            // Precio USD fijo (19/08/2026) — ver brain/PLAN-PRECIO-USD-FIJO-19082026.md.
            'price_usd'       => 'nullable|numeric|min:0',
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
