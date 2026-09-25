<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SmartStand (25/09/2026) — alta MANUAL de una empresa expositora por el
 * organizador. La autorización real (scoping por evento) la hace
 * assertCanWriteEvento() en el controller, igual que el resto de este proyecto.
 * No recibe contraseña: se genera y se manda por correo.
 */
class StoreEmpresaExpositoraRequest extends FormRequest
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
        $eventoId = $this->route('event')?->id;

        return [
            'nombre'       => ['required', 'string', 'max:255'],
            'email'        => [
                'required', 'email', 'max:255',
                Rule::unique('empresas_expositoras', 'email')->where('evento_id', $eventoId),
            ],
            'stand'        => ['nullable', 'string', 'max:255'],
            'categoria_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where('event_id', $eventoId),
            ],
            'activo'       => ['sometimes', 'boolean'],
        ];
    }
}
