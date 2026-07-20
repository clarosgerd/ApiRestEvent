<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaidRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirmacion'                              => ['required', 'boolean'],

            'participantes'                              => ['required', 'array', 'min:1'],
            'participantes.*.nombre'                     => ['required', 'string'],
            'participantes.*.apellido'                   => ['required', 'string'],
            'participantes.*.alias'                      => ['nullable', 'string'],
            'participantes.*.genero'                     => ['nullable', 'string'],
            'participantes.*.tipoDocumento'              => ['nullable', 'string'],
            'participantes.*.numeroDocumento'            => ['required', 'string'],
            'participantes.*.polera'                     => ['nullable', 'string'],
            'participantes.*.precioPolera'               => ['nullable', 'numeric'],
            'participantes.*.nacimiento'                 => ['required', 'array'],
            'participantes.*.nacimiento.dia'             => ['required', 'numeric'],
            'participantes.*.nacimiento.mes'             => ['required', 'numeric'],
            'participantes.*.nacimiento.anio'            => ['required', 'numeric'],
            'participantes.*.edad'                       => ['required', 'numeric'],
            'participantes.*.correo'                     => ['required', 'email'],
            'participantes.*.direccion'                  => ['nullable', 'string'],
            'participantes.*.ciudad'                     => ['nullable', 'string'],
            'participantes.*.telefono'                   => ['nullable', 'string'],
            'participantes.*.categoria'                  => ['required'],
            'participantes.*.precioCategoria'            => ['required', 'numeric'],
            'participantes.*.donacion'                   => ['nullable', 'numeric'],
            'participantes.*.promoDescuento'             => ['nullable', 'numeric'],
            'participantes.*.promoCodigo'                => ['nullable', 'string'],
            'participantes.*.subtotal'                   => ['required', 'numeric'],
            'participantes.*.contacto_emergencia'        => ['required', 'array'],
            'participantes.*.contacto_emergencia.*'      => ['required', 'string'],
            'participantes.*.souvenirs'                  => ['nullable', 'array'],
            'participantes.*.answers'                    => ['nullable', 'array'],
            'participantes.*.answers.*.form_types_id'    => ['required_with:participantes.*.answers|integer'],
            'participantes.*.answers.*.question_id'      => ['required_with:participantes.*.answers|integer'],
            'participantes.*.answers.*.value'            => ['required_with:participantes.*.answers|string'],

            'totales'                                   => ['required', 'array'],
            'totales.inscripcion'                       => ['required', 'numeric'],
            'totales.donacion'                          => ['required', 'numeric'],
            'totales.souvenirs'                         => ['required', 'numeric'],
            'totales.fee'                               => ['required', 'numeric'],
            'totales.descuento'                         => ['required', 'numeric'],
            'totales.descuento_registrante'              => ['nullable', 'numeric'],
            'totales.grand_total'                       => ['required', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmacion.required' => 'Debe confirmar que acepta el costo adicional de edición para proceder.',
            'confirmacion.boolean'  => 'El campo confirmación debe ser verdadero o falso.',
            'participantes.required'          => 'Debe existir al menos un participante.',
            'participantes.min'               => 'Debe existir al menos un participante.',
            'participantes.*.correo.email'    => 'Correo inválido.',
            'totales.required'                => 'Los totales son obligatorios.',
        ];
    }
}
