<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SmartStand (25/09/2026) — edición de una empresa expositora por el
 * organizador. No cambia la contraseña (para eso está "Reenviar credenciales").
 */
class UpdateEmpresaExpositoraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $empresa = $this->route('empresaExpositora');

        return [
            'nombre'       => ['sometimes', 'string', 'max:255'],
            'email'        => [
                'sometimes', 'email', 'max:255',
                Rule::unique('empresas_expositoras', 'email')
                    ->where('evento_id', $empresa?->evento_id)
                    ->ignore($empresa?->id),
            ],
            'stand'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'categoria_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('categories', 'id')->where('event_id', $empresa?->evento_id),
            ],
            'activo'       => ['sometimes', 'boolean'],
        ];
    }
}
