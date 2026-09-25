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
            // Ver StoreEventosRequest — mismo motivo, 500 quedaba corto.
            'longDescription'  => 'sometimes|nullable|string|max:10000',
            'date'             => 'sometimes|date',
            'localTime'        => 'sometimes|nullable|string',
            'location'         => 'sometimes|string|max:500',
            'status'           => 'sometimes|nullable|string|in:open,closed,coming_soon',
            'hasDonation'      => 'sometimes|boolean',
            // Inscripción en BOB y USD (18/08/2026) — ver
            // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md.
            'aceptaUsd'        => 'sometimes|boolean',
            // Congresos con talleres (19/08/2026) — ver EventoService::update().
            'talleresConCosto' => 'sometimes|boolean',
            // Cargo de servicio sobre talleres (19/08/2026) — ver EventoService::update().
            'feeIncluyeTalleres' => 'sometimes|boolean',
            // Precio USD fijo (19/08/2026) — ver EventoService::update().
            'usdPrecioFijo'    => 'sometimes|boolean',
            // "Pagar en el evento (efectivo)" al agregar un taller a una
            // inscripción pagada — configurable por evento (02/09/2026) —
            // ver EventoService::update().
            'forzarQrPagoAdicional' => 'sometimes|boolean',
            // Purgar datos de Persona/Participante en inscripciones
            // canceladas (01/09/2026) — ver
            // PurgarDatosPersonaCanceladaAction.
            'mantenerDatosPersona' => 'sometimes|boolean',
            // Orden de secciones en #screen-form-types (25/08/2026) — ver
            // EventoService::update(), mismas 9 claves fijas del frontend.
            'seccionesOrden'   => 'sometimes|nullable|array',
            'seccionesOrden.*' => 'string|in:description,calendar,countdown,media,sponsors,kitGallery,routeMap,agenda,formTypes',
            // Gafetes/certificados parametrizables por evento (13/09/2026) —
            // pedido real de COLABIOCLI 2026, ver EventoService::update().
            'certificadoSoloNombre'   => 'sometimes|boolean',
            'gafeteConfig'            => 'sometimes|nullable|array',
            // tipo (23/09/2026) — 'completo' (default, nombre+QR+rol) vs
            // 'label' (solo QR, para impresora de etiquetas/pegatinas
            // sobre un gafete físico predefinido). min bajado de 3 a 2:
            // una pegatina de QR puede ser más chica que un gafete completo.
            'gafeteConfig.tipo'       => 'sometimes|in:completo,label',
            'gafeteConfig.width_cm'   => 'sometimes|numeric|min:2|max:15',
            'gafeteConfig.height_cm'  => 'sometimes|numeric|min:2|max:15',
            'gafeteConfig.per_row'    => 'sometimes|integer|min:1|max:6',
            'gafeteConfig.paper'      => 'sometimes|in:a4,letter',
            'gafeteConfig.orientation' => 'sometimes|in:portrait,landscape',
            // SmartStand (25/09/2026) — configuración de expositores del evento.
            'expositoresConfig'                  => 'sometimes|nullable|array',
            'expositoresConfig.app_url_ios'      => 'sometimes|nullable|url|max:500',
            'expositoresConfig.app_url_android'  => 'sometimes|nullable|url|max:500',
            'expositoresConfig.instrucciones'    => 'sometimes|nullable|string|max:1000',
            'expositoresConfig.dashboard_url'    => 'sometimes|nullable|url|max:500',
            // Fase 4 (26/09/2026): mapa de especialidades, pasaporte médico y correo de seguimiento.
            'expositoresConfig.especialidad_pregunta'        => 'sometimes|nullable|string|max:255',
            'expositoresConfig.institucion_pregunta'         => 'sometimes|nullable|string|max:255',
            'expositoresConfig.pasaporte_min_stands'         => 'sometimes|nullable|integer|between:1,50',
            // Sin la confirmación de Términos el seguimiento no se puede encender.
            'expositoresConfig.seguimiento_habilitado'       => ['sometimes', 'nullable', 'boolean', function ($attribute, $value, $fail) {
                if (filter_var($value, FILTER_VALIDATE_BOOLEAN) && ! $this->input('expositoresConfig.seguimiento_tyc_confirmado_at')) {
                    $fail('Para habilitar el correo de seguimiento debes confirmar que los Términos y Condiciones del evento informan que los expositores pueden contactar al asistente.');
                }
            }],
            'expositoresConfig.seguimiento_tyc_confirmado_at' => 'sometimes|nullable|date',
            'expositoresConfig.seguimiento_max_por_asistente' => 'sometimes|nullable|integer|between:1,100',
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
