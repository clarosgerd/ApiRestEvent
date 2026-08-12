<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'razon_social'      => 'sometimes|required|string|max:150',
            'nombre_comercial'  => 'sometimes|nullable|string|max:100',
            'rut_nit'           => 'sometimes|nullable|string|max:30',
            'email'             => 'sometimes|required|email|max:150',
            'telefono'          => 'sometimes|nullable|string|max:30',
            'pais_id'           => 'sometimes|nullable|integer|exists:paises,id',
            'ciudad_id'         => 'sometimes|nullable|integer|exists:ciudades,id',
            'direccion'         => 'sometimes|nullable|string|max:255',
            'logo_url'          => 'sometimes|nullable|string|max:255',
            'plan_id'           => 'sometimes|nullable|integer|min:1',
            'comision_especial' => 'sometimes|nullable|numeric|min:0|max:100',
            'convenio_notas'    => 'sometimes|nullable|string',
            'activo'            => 'sometimes|nullable|boolean',
        ];
    }
}
