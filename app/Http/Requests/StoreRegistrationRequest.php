<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaContactoEmergenciaCondicional;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationRequest extends FormRequest
{
    use ValidaContactoEmergenciaCondicional;

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
            //
             '0' => ['required', 'array'],
             '*.referencia' => ['required', 'string'],
            '*.fecha' => ['required','date'],
            '*.evento_id' => ['required'],
            '*.form_types_id' => ['required', 'integer'],
            '*.evento_nombre' => ['required','string'],
            '*.tipo_pago' => ['required'],
            '*.pago_status' => ['required'],
            '*.pay_order_number' => ['nullable', 'string'],
            '*.totales' => ['required','array'],
            // Caja para eventos tipo congreso (20/08/2026) — obligatoriedad
            // condicional por form_type, ver withValidator() más abajo.
            '*.participantes.*.contacto_emergencia' => ['nullable','array'],
            '*.participantes.*.contacto_emergencia.*' => ['nullable','string'],
            '*.participantes.*.souvenirs' => ['nullable','array'],
            // Congresos con talleres (18/08/2026) — bug real encontrado el
            // 19/08/2026: esta regla nunca existió, así que
            // $request->validated() descartaba `talleres` en silencio (mismo
            // motivo por el que `souvenirs`/`answers` sí sobrevivían: son las
            // únicas claves anidadas que Laravel conserva son las que tienen
            // su propia regla). Ninguna inscripción con taller llegaba a
            // CrearInscripcionAction con el array real — siempre vacío, así
            // que nunca se creaba la fila en participante_taller_sesion. Los
            // tests existentes (TallerSeleccionInscripcionTest, etc.) no lo
            // agarraron porque llaman a CrearInscripcionAction::handle()
            // directo, saltándose esta capa de validación HTTP.
            '*.participantes.*.talleres' => ['nullable','array'],
            '*.participantes.*.answers' => ['nullable','array'],
            '*.participantes.*.answers.*.form_types_id' => ['required_with:*.participantes.*.answers|integer'],
            '*.participantes.*.answers.*.question_id' => ['required_with:*.participantes.*.answers|integer'],
            '*.participantes.*.answers.*.value' => ['required_with:*.participantes.*.answers|string'],
            '*.participantes' => ['required','array','min:1'],
            '*.participantes.*.nombre' => ['required'],
            '*.participantes.*.apellido' => ['required'],
            '*.participantes.*.correo' => ['required','email'],
            '*.participantes.*.numeroDocumento' => ['required'],
            '*.participantes.*.categoria' => ['required'],
            '*.participantes.*.equipoId' => ['nullable','integer'],
            '*.participantes.*.quiereDelivery' => ['nullable','boolean'],
            '*.participantes.*.precioCategoria' => ['required','numeric'],
            '*.participantes.*.nacimiento' => ['required','array'],
            '*.participantes.*.nacimiento.dia' => ['required','numeric'],
            '*.participantes.*.nacimiento.mes' => ['required','numeric'],
            '*.participantes.*.nacimiento.anio' => ['required','numeric'],
             '*.participantes.*.edad' => ['required','numeric'],
         
          '*.participantes.*.promoDescuento' => ['required'],
          '*.participantes.*.promoCodigo' => ['nullable'],
           '*.participantes.*.polera' => ['required'],
           '*.participantes.*.precioPolera' => ['required'],
           '*.participantes.*.direccion' => ['required'],
           '*.participantes.*.ciudad' => ['required'],
           '*.participantes.*.telefono' => ['required'],
           '*.participantes.*.donacion' => ['required'],
           '*.participantes.*.subtotal' => ['required'],
         
        ];
    }
    public function messages(): array
    {
        return [

            '*.referencia.required' => 'La referencia es obligatoria.',
            '*.participantes.required' => 'Debe existir al menos un participante.',
            '*.participantes.*.correo.email' => 'Correo inválido.'

        ];
    }

    /**
     * Caja para eventos tipo congreso (20/08/2026) — contacto de
     * emergencia obligatorio solo si `form_types.requiere_contacto_emergencia`
     * lo pide (default true). No se puede expresar como regla declarativa
     * porque depende de una consulta a `form_types` por cada registro del
     * array raíz (payload es `[{form_types_id, participantes: [...]}]`).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->all() as $i => $registro) {
                if (!is_array($registro) || !$this->formTypeRequiereContactoEmergencia($registro['form_types_id'] ?? null)) {
                    continue;
                }
                foreach ($registro['participantes'] ?? [] as $j => $participante) {
                    foreach ($this->camposContactoEmergenciaFaltantes($participante['contacto_emergencia'] ?? []) as $campo) {
                        $validator->errors()->add(
                            "{$i}.participantes.{$j}.contacto_emergencia.{$campo}",
                            'El contacto de emergencia es obligatorio para este tipo de inscripción.'
                        );
                    }
                }
            }
        });
    }
}
