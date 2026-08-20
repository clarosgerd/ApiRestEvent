<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFormularioCamposRequest extends FormRequest
{
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
            'seccion'            => 'sometimes|required|in:personal,kit,encuesta,legal,otro',
            'nombre_campo'       => 'sometimes|required|string|max:255',
            'etiqueta'           => 'sometimes|required|string|max:255',
            'tipo_input'         => 'sometimes|required|in:text,email,tel,date,number,select,checkbox,radio,textarea',
            'placeholder'        => 'nullable|string|max:255',
            'obligatorio'        => 'sometimes|boolean',
            'visible_en_reporte' => 'sometimes|boolean',
            'orden'              => 'nullable|integer|min:0',
            'options'               => 'sometimes|array',
            'options.*.option_text' => 'required_with:options|string|max:255',
            'options.*.order'       => 'nullable|integer|min:0',
        ];
    }
}
