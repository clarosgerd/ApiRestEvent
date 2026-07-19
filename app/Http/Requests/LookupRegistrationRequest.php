<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LookupRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'evento_id'   => ['required', 'integer'],
            'form_type_id' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'       => 'El correo electrónico es obligatorio.',
            'email.email'          => 'El correo electrónico no es válido.',
            'password.required'    => 'La contraseña es obligatoria.',
            'evento_id.required'   => 'El ID del evento es obligatorio.',
            'evento_id.integer'    => 'El ID del evento debe ser un número entero.',
            'form_type_id.required' => 'El ID del tipo de formulario es obligatorio.',
            'form_type_id.integer'  => 'El ID del tipo de formulario debe ser un número entero.',
        ];
    }
}
