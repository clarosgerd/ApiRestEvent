<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterPersonaRequest extends FormRequest
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
            'nombre' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:personas',
            'apellido' => 'required|string|max:255',
            'alias' => 'required|string|max:255',
            'sexo' => 'required|string|max:255',
            'tipo_documento' => 'required|string|max:255',
            'numero_documento' => 'required|string|max:255',
            'fecha_nacimiento' => 'required|date',
            'correo' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
            'ciudad' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:255',
            'celular' => 'nullable|string|max:255',

          //  'email' => 'required|string|email|max:255',
            'password' => 'required|string',
        ];
    }
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede tener más de 255 caracteres.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Este email ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'apellido.required' => 'El apellido es obligatorio.',
            'apellido.max' => 'El apellido no puede tener más de 255 caracteres.',
            'alias.required' => 'El alias es obligatorio.',
            'alias.max' => 'El alias no puede tener más de 255 caracteres.',
            'sexo.required' => 'El sexo es obligatorio.',
            'sexo.max' => 'El sexo no puede tener más de 255 caracteres.',
            'tipo_documento.required' => 'El tipo de documento es obligatorio.',
            'tipo_documento.max' => 'El tipo de documento no puede tener más de 255 caracteres.',
            'numero_documento.required' => 'El número de documento es obligatorio.',
            'numero_documento.max' => 'El número de documento no puede tener más de 255 caracteres.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida.',
            'correo.required' => 'El correo es obligatorio.',
            'correo.max' => 'El correo no puede tener más de 255 caracteres.',
            'direccion.required' => 'La dirección es obligatoria.',
            'direccion.max' => 'La dirección no puede tener más de 255 caracteres.',
            'ciudad.required' => 'La ciudad es obligatoria.',
            'ciudad.max' => 'La ciudad no puede tener más de 255 caracteres.',
            'telefono.max' => 'El teléfono no puede tener más de 255 caracteres.',
            'celular.max' => 'El celular no puede tener más de 255 caracteres.',    
        ];
    }
}
