<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CRUD de personas (21/08/2026) — edición desde el panel de
 * administración, solo super_admin (autorización real en
 * PersonaController::assertIsSuperAdmin()). `sometimes` en todos los
 * campos: es una edición parcial, no hace falta reenviar el registro
 * completo — mismo criterio que UpdateParticipanteRequest.
 */
class UpdatePersonaRequest extends FormRequest
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
            'nombre' => 'sometimes|string|max:255',
            'apellido' => 'sometimes|string|max:255',
            'alias' => 'sometimes|nullable|string|max:255',
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('personas', 'email')->ignore($this->route('persona'))],
            'password' => 'sometimes|nullable|string|min:6',
            'sexo' => 'sometimes|string|max:255',
            'tipo_documento' => 'sometimes|in:DNI,CI,Pasaporte',
            'numero_documento' => 'sometimes|string|max:255',
            'fecha_nacimiento' => 'sometimes|date',
            'direccion' => 'sometimes|nullable|string|max:255',
            'ciudad' => 'sometimes|nullable|string|max:255',
            'telefono' => 'sometimes|nullable|string|max:255',
            'celular' => 'sometimes|nullable|string|max:255',
            'acepta_marketing' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Este email ya está registrado.',
            'tipo_documento.in' => 'El tipo de documento debe ser DNI, CI o Pasaporte.',
            'fecha_nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida.',
        ];
    }
}
