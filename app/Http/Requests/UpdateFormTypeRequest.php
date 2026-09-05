<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFormTypeRequest extends FormRequest
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
     * Mismo set de campos que StoreFormTypeRequest, todos opcionales — no
     * incluye souvenirs/preguntas (esos se administran vía SouvenirController
     * / preguntas del formulario, no se resincronizan en un PUT/PATCH de
     * form_type suelto).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'                       => 'sometimes|string|max:255',
            'icon'                       => 'sometimes|string|max:255',
            // Tarjeta de tipo de formulario simplificada (19/08/2026).
            'imagen_url'                 => 'sometimes|nullable|string|max:500',
            'description'                => 'sometimes|string',
            'tipo'                       => 'sometimes|nullable|string',
            'cupo_total'                 => 'sometimes|integer|min:0',
            'precio_base'                => 'sometimes|numeric|min:0',
            'costo_edicion'              => 'sometimes|numeric|min:0',
            'tiempo_expiracion_min'      => 'sometimes|integer|min:0',
            'color'                      => 'sometimes|string|max:7',
            'moneda'                     => 'sometimes|nullable|boolean',
            'activo'                     => 'sometimes|nullable|boolean',
            'permite_lista_espera'       => 'sometimes|nullable|boolean',
            'requiere_categoria'         => 'sometimes|nullable|boolean',
            'requiere_talla'             => 'sometimes|nullable|boolean',
            'requiere_distancia'         => 'sometimes|nullable|boolean',
            'hasshirt'                   => 'sometimes|nullable|boolean',
            'costo_polera'               => 'sometimes|nullable|numeric|min:0',
            'permite_inscripcion_grupal' => 'sometimes|nullable|boolean',
            'max_integrantes_grupo'      => 'sometimes|nullable|integer|min:1',
            'descuento_registrante_pct'  => 'sometimes|nullable|numeric|min:0|max:1',
            'hasQuestion'                => 'sometimes|nullable|boolean',
            'texto_boton'                => 'sometimes|nullable|string|max:60',
            'has_team'                   => 'sometimes|nullable|boolean',
            'has_delivery'               => 'sometimes|nullable|boolean',
            'has_donation'               => 'sometimes|nullable|boolean',
            'has_promo_code'             => 'sometimes|nullable|boolean',
            'es_staff'                   => 'sometimes|nullable|boolean',
            'es_ponente'                 => 'sometimes|nullable|boolean',
            'requiere_contacto_emergencia' => 'sometimes|nullable|boolean',
            // Ocultar Dirección/Ciudad/Teléfono/Alias por tipo de
            // formulario (01/09/2026) — ver PLAN-OCULTAR-CAMPOS-FORM-TYPE-01092026.md.
            'campos_ocultos'             => 'sometimes|nullable|array',
            'campos_ocultos.*'           => 'string|in:direccion,ciudad,telefono,alias',
            // Edición restringida a solo souvenirs/talleres (04/09/2026).
            'edicion_solo_extras'        => 'sometimes|nullable|boolean',
        ];
    }
}
