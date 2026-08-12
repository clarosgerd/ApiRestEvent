<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'razon_social'      => 'required|string|max:150',
            'nombre_comercial'  => 'nullable|string|max:100',
            'rut_nit'           => 'nullable|string|max:30',
            'email'             => 'required|email|max:150',
            'telefono'          => 'nullable|string|max:30',
            'pais_id'           => 'nullable|integer|exists:paises,id',
            'ciudad_id'         => 'nullable|integer|exists:ciudades,id',
            'direccion'         => 'nullable|string|max:255',
            'logo_url'          => 'nullable|string|max:255',
            'plan_id'           => 'nullable|integer|min:1',
            'comision_especial' => 'nullable|numeric|min:0|max:100',
            'convenio_notas'    => 'nullable|string',
            'activo'            => 'nullable|boolean',
        ];
    }
}
