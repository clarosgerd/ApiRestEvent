<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSouvenirRequest extends FormRequest
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
            'form_types_id'   => 'required|integer|exists:form_types,id',
            'name'            => 'required|string|max:255',
            'icon'            => 'nullable|string|max:10',
            'price'           => 'required|numeric|min:0',
            // Kit/tallas/stock (11/08/2026) — ver
            // PRD-kit-tallas-stock-lista-espera.md.
            'incluido'        => 'nullable|boolean',
            'foto_url'        => 'nullable|string|max:2048|url',
            'requiere_talla'  => 'nullable|boolean',
            'requiere_sexo'   => 'nullable|boolean',
            // Souvenirs invisibles para el participante (22/08/2026) — ver
            // migración add_visible_participante_to_souvenirs_table.
            'visible_participante' => 'nullable|boolean',
            // Cargo de servicio por souvenir individual (01/09/2026) — ver
            // migración add_aplica_cargo_servicio_to_souvenirs_table.
            'aplica_cargo_servicio' => 'nullable|boolean',
            // Texto promocional por souvenir (02/09/2026) — ver migración
            // add_texto_promocional_to_souvenirs_table.
            'texto_promocional' => 'nullable|string|max:500',
        ];
    }

    /**
     * Un souvenir invisible se asigna automático a todos los participantes
     * del form_type (ver RegistrationService::injectSouvenirsInvisibles())
     * — nunca pasa por una tarjeta seleccionable en el formulario, así que
     * no tiene sentido pedirle talla/sexo a nadie.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $visible = $this->boolean('visible_participante', true);
            if (! $visible && ($this->boolean('requiere_talla') || $this->boolean('requiere_sexo'))) {
                $validator->errors()->add(
                    'visible_participante',
                    'Un souvenir invisible para el participante no puede requerir talla/sexo — nunca hay quién los elija.'
                );
            }
        });
    }
}
