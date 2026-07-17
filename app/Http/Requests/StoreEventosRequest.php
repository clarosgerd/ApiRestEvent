<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                  => 'required|string|max:255',
            'description'           => 'required|string|max:500',
            'longDescription'       => 'nullable|string|max:500',
            'date'                  => 'required|date',
            'localTime'             => 'nullable|string',
            'location'              => 'required|string|max:500',
            'hasDonation'           => 'nullable|boolean',
            'video'                 => 'nullable|string|max:255',
            'image'                 => 'nullable|string|max:255',

            'coordinates'           => 'nullable|array',
            'coordinates.*.lat'     => 'required_with:coordinates|numeric',
            'coordinates.*.lng'     => 'required_with:coordinates|numeric',

            'route'                 => 'nullable|array',
            'route.*.lat'           => 'required_with:route|numeric',
            'route.*.lng'           => 'required_with:route|numeric',
            'route.*.label'         => 'nullable|string',

            'categories'            => 'nullable|array',
            'categories.*.name'     => 'required_with:categories|string|max:255',
            'categories.*.price'    => 'required_with:categories|numeric',
            'categories.*.description' => 'nullable|string',
            'categories.*.color'    => 'nullable|string|max:7',

            'formTypes'             => 'nullable|array',
            'formTypes.*.name'      => 'required_with:formTypes|string|max:255',
            'formTypes.*.icon'      => 'nullable|string',
            'formTypes.*.description' => 'nullable|string',
            'formTypes.*.tipo'      => 'nullable|string',
            'formTypes.*.cupo_total' => 'required_with:formTypes|integer|min:0',
            'formTypes.*.precio_base' => 'required_with:formTypes|numeric|min:0',
            'formTypes.*.color'     => 'nullable|string|max:7',
            'formTypes.*.moneda'    => 'nullable|integer',
            'formTypes.*.permite_lista_espera' => 'nullable|integer',
            'formTypes.*.hasshirt'  => 'nullable|integer',
            'formTypes.*.requiere_talla' => 'nullable|integer',

            'formTypes.*.souvenirs'           => 'nullable|array',
            'formTypes.*.souvenirs.*.name'    => 'required_with:formTypes.*.souvenirs|string|max:255',
            'formTypes.*.souvenirs.*.icon'    => 'nullable|string',
            'formTypes.*.souvenirs.*.price'   => 'required_with:formTypes.*.souvenirs|numeric|min:0',

            'promoCodes'            => 'nullable|array',
            'promoCodes.*.promo_code' => 'required_with:promoCodes|string|max:30',
            'promoCodes.*.price'    => 'required_with:promoCodes|numeric|min:0',
        ];
    }
}
