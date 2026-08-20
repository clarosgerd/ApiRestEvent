<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFormularioCamposRequest extends FormRequest
{
    /**
     * true — la autorización real es AuthorizesEventoScope dentro del
     * controller (mismo criterio que StoreSesionCongresoRequest y el
     * resto de los Store*Request de este panel).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'seccion'            => 'required|in:personal,kit,encuesta,legal,otro',
            'nombre_campo'       => 'required|string|max:255',
            'etiqueta'           => 'required|string|max:255',
            // 'file' queda fuera a propósito — el formulario público
            // (elascenso/event/index.php::renderDynamicField) lo omite en
            // silencio, no hay endpoint de subida de archivos todavía.
            'tipo_input'         => 'required|in:text,email,tel,date,number,select,checkbox,radio,textarea',
            'placeholder'        => 'nullable|string|max:255',
            'obligatorio'        => 'sometimes|boolean',
            'visible_en_reporte' => 'sometimes|boolean',
            'orden'              => 'nullable|integer|min:0',
            // Opciones — obligatorias para los 3 tipos que las usan.
            'options'               => 'required_if:tipo_input,select,checkbox,radio|array',
            'options.*.option_text' => 'required_with:options|string|max:255',
            'options.*.order'       => 'nullable|integer|min:0',
        ];
    }
}
