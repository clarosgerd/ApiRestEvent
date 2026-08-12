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
            'chronotrackEventId' => 'sometimes|nullable|string|max:50',
            'deslinde'         => 'sometimes|nullable|string|max:500',
            'deslinde_pdf_url' => 'sometimes|nullable|string|max:500',
            'tipo_evento_id'    => 'sometimes|nullable|integer|exists:tipos_evento,id',
            'subtipo_evento_id' => 'sometimes|nullable|integer|exists:subtipos_evento,id',
            // CRUD de organizadores (11/08/2026) — solo super_admin puede
            // mandar este campo, y solo si el evento todavía no está
            // publicado (ver EventoController::update()); este Request
            // solo valida el formato/existencia.
            'organizador_id'    => 'sometimes|nullable|integer|exists:organizadores,id',
            // Cargo de servicio (11/08/2026) — fracción, no porcentaje
            // entero (0.05 = 5%). Tope en 0.20 (20%) como red de
            // seguridad ante un error de tipeo, no un límite de negocio
            // pedido — si hace falta más, se ajusta. Solo super_admin
            // puede mandar este campo (ver EventoController::update()),
            // este Request solo valida el formato.
            'feePct'            => 'sometimes|numeric|min:0|max:0.20',
        ];
    }
}
