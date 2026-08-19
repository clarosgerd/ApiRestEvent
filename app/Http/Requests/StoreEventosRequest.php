<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `nullable` solo exime el valor `null` real — un `url_slug=""` (campo
     * de formulario dejado en blanco) sigue siendo un string y rompería el
     * `regex` de abajo. Se normaliza a `null` acá para que "vacío" siempre
     * signifique "auto-generar" (ver CrearEventoAction).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('url_slug') && trim((string) $this->input('url_slug')) === '') {
            $this->merge(['url_slug' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'organizador_id'        => 'nullable|integer|exists:organizadores,id',
            'tipo_evento_id'        => 'nullable|integer|exists:tipos_evento,id',
            'subtipo_evento_id'     => 'nullable|integer|exists:subtipos_evento,id',
            'name'                  => 'required|string|max:255',
            'description'           => 'required|string|max:500',
            // Descripción larga (19/08/2026) — el límite de 500 quedaba corto
            // para el uso real ("descripción larga" debía admitir más que la
            // corta de arriba); la columna es TEXT (hasta 65535 bytes), así
            // que 10000 caracteres es solo un techo razonable de captura, no
            // una limitación real de la BD.
            'longDescription'       => 'nullable|string|max:10000',
            'date'                  => 'required|date',
            'localTime'             => 'nullable|string',
            'location'              => 'required|string|max:500',
            'status'                => 'nullable|string|in:open,closed,coming_soon',
            'publicado'             => 'nullable|boolean',
            'hasDonation'           => 'nullable|boolean',
            // Inscripción en BOB y USD (18/08/2026) — ver
            // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Si el
            // organizador habilita pago en USD (extranjeros). Default
            // false si no se manda (eventos existentes siguen BOB-only).
            'aceptaUsd'             => 'nullable|boolean',
            // Congresos con talleres (19/08/2026) — ver EventoService::update()
            // y ResolverPrecioTallerData. Default false (los talleres se
            // configuran después de crear el evento).
            'talleresConCosto'      => 'nullable|boolean',
            // Cargo de servicio sobre talleres (19/08/2026) — ver EventoDTO/CrearEventoAction.
            'feeIncluyeTalleres'    => 'nullable|boolean',
            'video'                 => 'nullable|string|max:255',
            'image'                 => 'nullable|string|max:255',
            'colorHex'              => 'nullable|string|max:7',
            'chronotrackEventId'    => 'nullable|string|max:50',
            'deslinde'              => 'nullable|string|max:500',
            'deslinde_pdf_url'      => 'nullable|string|max:500',
            // Link directo al evento (18/08/2026) — ver elascenso/event,
            // Evento::resolveRouteBinding(). Vacío/ausente = se
            // auto-genera desde `name` (ver CrearEventoAction).
            'url_slug'              => 'nullable|string|max:255|regex:/^[a-z0-9]+(-[a-z0-9]+)*$/|unique:eventos,url_slug',

            'coordinates'           => 'nullable|array',
            'coordinates.*.lat'     => 'required_with:coordinates|numeric',
            'coordinates.*.lng'     => 'required_with:coordinates|numeric',

            'route'                 => 'nullable|array',
            'route.*.lat'           => 'required_with:route|numeric',
            'route.*.lng'           => 'required_with:route|numeric',
            'route.*.label'         => 'nullable|string',

            'categories'            => 'required|array|min:1',
            'categories.*.name'     => 'required|string|max:255',
            'categories.*.price'    => 'required|numeric|min:0',
            'categories.*.description' => 'nullable|string',
            'categories.*.color'    => 'nullable|string|max:7',

            'formTypes'             => 'nullable|array',
            'formTypes.*.name'      => 'required_with:formTypes|string|max:255',
            'formTypes.*.icon'      => 'nullable|string',
            // Tarjeta de tipo de formulario simplificada (19/08/2026).
            'formTypes.*.imagen_url' => 'nullable|string|max:500',
            'formTypes.*.description' => 'nullable|string',
            'formTypes.*.tipo'      => 'nullable|string',
            'formTypes.*.cupo_total' => 'required_with:formTypes|integer|min:0',
            'formTypes.*.precio_base' => 'required_with:formTypes|numeric|min:0',
            'formTypes.*.color'     => 'nullable|string|max:7',
            'formTypes.*.costo_edicion'         => 'nullable|numeric|min:0',
            'formTypes.*.tiempo_expiracion_min' => 'nullable|integer|min:0',
            'formTypes.*.moneda'    => 'nullable|integer',
            'formTypes.*.permite_lista_espera' => 'nullable|integer',
            'formTypes.*.hasshirt'  => 'nullable|integer',
            'formTypes.*.costo_polera' => 'nullable|numeric|min:0',
            'formTypes.*.requiere_talla' => 'nullable|integer',
            'formTypes.*.permite_inscripcion_grupal' => 'nullable|boolean',
            'formTypes.*.max_integrantes_grupo'      => 'nullable|integer|min:1',
            'formTypes.*.descuento_registrante_pct'  => 'nullable|numeric|min:0|max:1',
            'formTypes.*.hasTeam'                    => 'nullable|boolean',
            'formTypes.*.hasDelivery'                => 'nullable|boolean',
            'formTypes.*.hasDonation'                => 'nullable|boolean',
            'formTypes.*.hasPromoCode'               => 'nullable|boolean',
            'formTypes.*.requiere_categoria'         => 'nullable|boolean',

            'formTypes.*.souvenirs'           => 'nullable|array',
            'formTypes.*.souvenirs.*.name'    => 'required_with:formTypes.*.souvenirs|string|max:255',
            'formTypes.*.souvenirs.*.icon'    => 'nullable|string',
            'formTypes.*.souvenirs.*.price'   => 'required_with:formTypes.*.souvenirs|numeric|min:0',

            'formTypes.*.preguntas'                      => 'nullable|array',
            'formTypes.*.preguntas.*.nombre_campo'       => 'required_with:formTypes.*.preguntas|string|max:255',
            'formTypes.*.preguntas.*.seccion'            => 'nullable|string|in:personal,kit,encuesta,legal,otro',
            'formTypes.*.preguntas.*.etiqueta'           => 'nullable|string|max:255',
            'formTypes.*.preguntas.*.tipo_input'         => 'nullable|string|in:text,email,tel,date,number,select,checkbox,radio,textarea,file',
            'formTypes.*.preguntas.*.placeholder'        => 'nullable|string|max:255',
            'formTypes.*.preguntas.*.obligatorio'        => 'nullable|boolean',
            'formTypes.*.preguntas.*.orden'              => 'nullable|integer',
            'formTypes.*.preguntas.*.options'            => 'nullable|array',
            'formTypes.*.preguntas.*.options.*.option_text' => 'required_with:formTypes.*.preguntas.*.options|string|max:255',
            'formTypes.*.preguntas.*.options.*.order'    => 'nullable|integer',

            'promoCodes'            => 'nullable|array',
            'promoCodes.*.promo_code' => 'required_with:promoCodes|string|max:30',
            'promoCodes.*.price'    => 'nullable|numeric|min:0',
            'promoCodes.*.discount_type'    => 'nullable|string|in:fixed_price,percentage',
            'promoCodes.*.discount_percent' => 'nullable|numeric|min:0|max:1',

            'auspiciadores'            => 'nullable|array',
            'auspiciadores.*.nombre'   => 'required_with:auspiciadores|string|max:255',
            'auspiciadores.*.logo_url' => 'required_with:auspiciadores|string|max:500',
            'auspiciadores.*.contacto' => 'nullable|string|max:500',
            'auspiciadores.*.orden'    => 'nullable|integer',

            'agenda'                   => 'nullable|array',
            'agenda.*.formTypeId'      => 'nullable|integer',
            'agenda.*.formTypeName'    => 'nullable|string|max:255',
            'agenda.*.date'            => 'nullable|date',
            'agenda.*.startTime'       => 'required_with:agenda|string',
            'agenda.*.endTime'         => 'nullable|string',
            'agenda.*.title'           => 'required_with:agenda|string|max:255',
            'agenda.*.description'     => 'nullable|string',
            'agenda.*.speaker'         => 'nullable|string|max:255',
            'agenda.*.speakerRole'     => 'nullable|string|max:255',
            'agenda.*.room'            => 'nullable|string|max:255',
            'agenda.*.icon'            => 'nullable|string|max:10',
            'agenda.*.orden'           => 'nullable|integer',
        ];
    }
}
