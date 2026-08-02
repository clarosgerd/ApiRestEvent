<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEventosRequest extends FormRequest
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
     * Solo los campos escalares del evento — categorías/form_types/promo_codes/
     * coordinates/route/agenda tienen sus propios endpoints sueltos (ver
     * CategoryController, FormTypeController, etc.), este request no los
     * resincroniza.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'             => 'sometimes|string|max:255',
            'description'      => 'sometimes|string|max:500',
            'longDescription'  => 'sometimes|nullable|string|max:500',
            'date'             => 'sometimes|date',
            'localTime'        => 'sometimes|nullable|string',
            'location'         => 'sometimes|string|max:500',
            'status'           => 'sometimes|nullable|string|in:open,closed,coming_soon',
            'hasDonation'      => 'sometimes|boolean',
            'video'            => 'sometimes|nullable|string|max:255',
            'image'            => 'sometimes|nullable|string|max:255',
            'colorHex'         => 'sometimes|nullable|string|max:7',
            'deslinde'         => 'sometimes|nullable|string|max:500',
            'deslinde_pdf_url' => 'sometimes|nullable|string|max:500',
        ];
    }
}
