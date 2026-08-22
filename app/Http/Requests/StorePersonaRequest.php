<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * CRUD de personas (21/08/2026) — alta manual de una Persona desde el
 * panel de administración, solo super_admin (la autorización real la
 * hace PersonaController::assertIsSuperAdmin(), mismo patrón que
 * Organizador/Socio). Mismas reglas que RegisterPersonaRequest (el alta
 * pública, mismo modelo), salvo `password`, que acá admite quedar vacía
 * — la persona creada a mano por un admin no necesariamente va a iniciar
 * sesión ella misma, se genera una aleatoria si no la mandan.
 */
class StorePersonaRequest extends FormRequest
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
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'alias' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:personas,email',
            'password' => 'nullable|string|min:6',
            'sexo' => 'required|string|max:255',
            'tipo_documento' => 'required|in:DNI,CI,Pasaporte',
            'numero_documento' => 'required|string|max:255',
            'fecha_nacimiento' => 'required|date',
            'direccion' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:255',
            'celular' => 'nullable|string|max:255',
            'acepta_marketing' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Este email ya está registrado.',
            'sexo.required' => 'El sexo es obligatorio.',
            'tipo_documento.required' => 'El tipo de documento es obligatorio.',
            'tipo_documento.in' => 'El tipo de documento debe ser DNI, CI o Pasaporte.',
            'numero_documento.required' => 'El número de documento es obligatorio.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida.',
        ];
    }
}
