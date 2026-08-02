<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAuspiciadorRequest extends FormRequest
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
            'event_id' => 'required|integer|exists:eventos,id',
            'nombre'   => 'required|string|max:255',
            'logo_url' => 'required|string|max:500',
            'contacto' => 'nullable|string|max:500',
            'orden'    => 'nullable|integer',
        ];
    }
}
