<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Caja: anular un cobro ya registrado (02/10/2026) — ver AnularCobroAction.
 * El motivo es siempre obligatorio acá (a diferencia de
 * UpdatePaidRegistrationRequest, donde solo es obligatorio en el caso
 * puntual de quitar un taller pagado) — toda anulación necesita una
 * justificación explícita.
 */
class AnularCobroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'movimiento_id' => ['required', 'integer'],
            'motivo'        => ['required', 'string', 'max:500'],
        ];
    }
}
