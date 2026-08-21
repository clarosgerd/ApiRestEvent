<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaContactoEmergenciaCondicional;
use App\Models\Registration;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRegistrationRequest extends FormRequest
{
    use ValidaContactoEmergenciaCondicional;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
            // Caja para eventos tipo congreso (20/08/2026) — obligatoriedad
            // condicional por form_type, ver withValidator() más abajo.
            'participantes.*.contacto_emergencia'        => ['nullable', 'array'],
            'participantes.*.contacto_emergencia.*'      => ['nullable', 'string'],
            'participantes.*.souvenirs'                  => ['nullable', 'array'],
            // Congresos con talleres (18/08/2026) — bug real encontrado el
            // 19/08/2026, ver StoreRegistrationRequest: sin esta regla,
            // $request->validated() descarta `talleres` en silencio, así
            // que editar una inscripción con talleres los perdía.
            'participantes.*.talleres'                   => ['nullable', 'array'],
            'participantes.*.answers'                    => ['nullable', 'array'],
            'participantes.*.answers.*.form_types_id'    => ['required_with:participantes.*.answers|integer'],
            'participantes.*.answers.*.question_id'      => ['required_with:participantes.*.answers|integer'],
            'participantes.*.answers.*.value'            => ['required_with:participantes.*.answers|string'],

            'totales'                                   => ['required', 'array'],
            'totales.inscripcion'                       => ['required', 'numeric'],
            'totales.donacion'                          => ['required', 'numeric'],
            'totales.souvenirs'                         => ['required', 'numeric'],
            'totales.talleres'                          => ['nullable', 'numeric'],
            'totales.fee'                               => ['required', 'numeric'],
            'totales.descuento'                         => ['required', 'numeric'],
            'totales.descuento_registrante'              => ['nullable', 'numeric'],
            'totales.grand_total'                       => ['required', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'participantes.required'          => 'Debe existir al menos un participante.',
            'participantes.min'               => 'Debe existir al menos un participante.',
            'participantes.*.correo.email'    => 'Correo inválido.',
            'totales.required'                => 'Los totales son obligatorios.',
        ];
    }

    /**
     * Caja para eventos tipo congreso (20/08/2026) — el `form_types_id`
     * no viaja en el body de un edit (no se puede cambiar), se resuelve
     * desde la inscripción existente vía el `{reference}` de la ruta.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $registration = Registration::where('referencia', $this->route('reference'))->first();
            if (!$this->formTypeRequiereContactoEmergencia($registration?->form_types_id)) {
                return;
            }
            foreach ($this->input('participantes', []) as $j => $participante) {
                foreach ($this->camposContactoEmergenciaFaltantes($participante['contacto_emergencia'] ?? []) as $campo) {
                    $validator->errors()->add(
                        "participantes.{$j}.contacto_emergencia.{$campo}",
                        'El contacto de emergencia es obligatorio para este tipo de inscripción.'
                    );
                }
            }
        });
    }
}
