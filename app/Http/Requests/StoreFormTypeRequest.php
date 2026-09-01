<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFormTypeRequest extends FormRequest
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
     * Cubre todo el $fillable real de FormType (más completo que las reglas
     * anidadas de StoreEventosRequest, que no exponen requiere_categoria —
     * ver brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_id'                   => 'required|integer|exists:eventos,id',
            'name'                       => 'required|string|max:255',
            'icon'                       => 'required|string|max:255',
            // Tarjeta de tipo de formulario simplificada (19/08/2026).
            'imagen_url'                 => 'nullable|string|max:500',
            'description'                => 'required|string',
            'tipo'                       => 'nullable|string',
            'cupo_total'                 => 'required|integer|min:0',
            'precio_base'                => 'required|numeric|min:0',
            'costo_edicion'              => 'required|numeric|min:0',
            'tiempo_expiracion_min'      => 'required|integer|min:0',
            'color'                      => 'required|string|max:7',
            'moneda'                     => 'nullable|boolean',
            'activo'                     => 'nullable|boolean',
            'permite_lista_espera'       => 'nullable|boolean',
            'requiere_categoria'         => 'nullable|boolean',
            'requiere_talla'             => 'nullable|boolean',
            'requiere_distancia'         => 'nullable|boolean',
            'hasshirt'                   => 'nullable|boolean',
            'costo_polera'               => 'nullable|numeric|min:0',
            'permite_inscripcion_grupal' => 'nullable|boolean',
            'max_integrantes_grupo'      => 'nullable|integer|min:1',
            'descuento_registrante_pct'  => 'nullable|numeric|min:0|max:1',
            'hasQuestion'                => 'nullable|boolean',
            'texto_boton'                => 'nullable|string|max:60',
            'has_team'                   => 'nullable|boolean',
            'has_delivery'               => 'nullable|boolean',
            'has_donation'               => 'nullable|boolean',
            'has_promo_code'             => 'nullable|boolean',
            'es_staff'                   => 'nullable|boolean',
            'es_ponente'                 => 'nullable|boolean',
            'requiere_contacto_emergencia' => 'nullable|boolean',
            // Ocultar Dirección/Ciudad/Teléfono/Alias por tipo de
            // formulario (01/09/2026) — ver PLAN-OCULTAR-CAMPOS-FORM-TYPE-01092026.md.
            'campos_ocultos'             => 'nullable|array',
            'campos_ocultos.*'           => 'string|in:direccion,ciudad,telefono,alias',

            'souvenirs'                  => 'nullable|array',
            'souvenirs.*.name'           => 'required_with:souvenirs|string|max:255',
            'souvenirs.*.icon'           => 'nullable|string',
            'souvenirs.*.price'          => 'required_with:souvenirs|numeric|min:0',

            'preguntas'                            => 'nullable|array',
            'preguntas.*.nombre_campo'             => 'required_with:preguntas|string|max:255',
            'preguntas.*.seccion'                  => 'nullable|string|in:personal,kit,encuesta,legal,otro',
            'preguntas.*.etiqueta'                 => 'nullable|string|max:255',
            'preguntas.*.tipo_input'               => 'nullable|string|in:text,email,tel,date,number,select,checkbox,radio,textarea,file',
            'preguntas.*.placeholder'              => 'nullable|string|max:255',
            'preguntas.*.obligatorio'              => 'nullable|boolean',
            'preguntas.*.orden'                    => 'nullable|integer',
            'preguntas.*.options'                  => 'nullable|array',
            'preguntas.*.options.*.option_text'    => 'required_with:preguntas.*.options|string|max:255',
            'preguntas.*.options.*.order'          => 'nullable|integer',
        ];
    }
}
