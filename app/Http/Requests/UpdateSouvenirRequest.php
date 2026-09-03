<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSouvenirRequest extends FormRequest
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
            'name'            => 'sometimes|string|max:255',
            'icon'            => 'sometimes|nullable|string|max:10',
            'price'           => 'sometimes|numeric|min:0',
            // Kit/tallas/stock (11/08/2026) — ver
            // PRD-kit-tallas-stock-lista-espera.md.
            'incluido'        => 'sometimes|boolean',
            'foto_url'        => 'sometimes|nullable|string|max:2048|url',
            'requiere_talla'  => 'sometimes|boolean',
            'requiere_sexo'   => 'sometimes|boolean',
            // Souvenirs invisibles para el participante (22/08/2026) — ver
            // migración add_visible_participante_to_souvenirs_table.
            'visible_participante' => 'sometimes|boolean',
            // Cargo de servicio por souvenir individual (01/09/2026) — ver
            // migración add_aplica_cargo_servicio_to_souvenirs_table.
            'aplica_cargo_servicio' => 'sometimes|boolean',
            // Texto promocional por souvenir (02/09/2026) — ver migración
            // add_texto_promocional_to_souvenirs_table.
            'texto_promocional' => 'sometimes|nullable|string|max:500',
            // Reporte de poleras (03/09/2026) — ver migración
            // add_es_polera_to_souvenirs_table.
            'es_polera' => 'sometimes|boolean',
        ];
    }

    /**
     * Mismo criterio que StoreSouvenirRequest, pero resolviendo los
     * campos no enviados en este update parcial contra el valor actual
     * del souvenir (route-model-binding ya resolvió {souvenir} para
     * cuando esto corre).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $souvenir = $this->route('souvenir');

            $visible = $this->has('visible_participante')
                ? $this->boolean('visible_participante')
                : (bool) ($souvenir->visible_participante ?? true);
            $requiereTalla = $this->has('requiere_talla')
                ? $this->boolean('requiere_talla')
                : (bool) ($souvenir->requiere_talla ?? false);
            $requiereSexo = $this->has('requiere_sexo')
                ? $this->boolean('requiere_sexo')
                : (bool) ($souvenir->requiere_sexo ?? false);
            $esPolera = $this->has('es_polera')
                ? $this->boolean('es_polera')
                : (bool) ($souvenir->es_polera ?? false);

            if (! $visible && ($requiereTalla || $requiereSexo)) {
                $validator->errors()->add(
                    'visible_participante',
                    'Un souvenir invisible para el participante no puede requerir talla/sexo — nunca hay quién los elija.'
                );
            }

            // Reporte de poleras (03/09/2026) — sin requiere_talla, la
            // talla del participante siempre queda null y el ítem no
            // aportaría nada útil al reporte.
            if ($esPolera && ! $requiereTalla) {
                $validator->errors()->add(
                    'es_polera',
                    'Un souvenir marcado como "es la polera" tiene que requerir talla — si no, el reporte no tendría de dónde sacarla.'
                );
            }
        });
    }
}
