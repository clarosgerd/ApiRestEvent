<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * `url_slug` es NOT NULL + único en `eventos` (a diferencia del resto
     * de los campos "nullable" de este Request, que sí pueden vaciarse).
     * Si el organizador deja el campo en blanco al editar, no hay un
     * "auto-generar" como en el alta — se interpreta como "sin cambios" y
     * se saca del payload, en vez de mandar `null`/`""` y romper el
     * UPDATE (columna NOT NULL) o la unicidad (dos eventos con `""`).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('url_slug') && trim((string) $this->input('url_slug')) === '') {
            $this->request->remove('url_slug');
        }
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
            // Inscripción en BOB y USD (18/08/2026) — ver
            // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md.
            'aceptaUsd'        => 'sometimes|boolean',
            'video'            => 'sometimes|nullable|string|max:255',
            'image'            => 'sometimes|nullable|string|max:255',
            'colorHex'         => 'sometimes|nullable|string|max:7',
            'chronotrackEventId' => 'sometimes|nullable|string|max:50',
            'deslinde'         => 'sometimes|nullable|string|max:500',
            'deslinde_pdf_url' => 'sometimes|nullable|string|max:500',
            // Link directo al evento (18/08/2026) — ver elascenso/event,
            // Evento::resolveRouteBinding(). `ignore($this->route('event'))`
            // deja que el evento se guarde a sí mismo sin chocar contra su
            // propio slug actual.
            'url_slug'         => [
                'sometimes', 'nullable', 'string', 'max:255',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('eventos', 'url_slug')->ignore($this->route('event')),
            ],
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
