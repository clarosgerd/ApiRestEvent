<?php

namespace App\Services;

use App\Models\Evento;

class EventoService
{
    /**
     * Actualiza los campos escalares del evento — no toca categorías,
     * form_types, promo_codes, coordenadas, ruta ni agenda, que tienen sus
     * propios endpoints de edición (ver Category/FormType/PromoCode/
     * Coordinate/RouteController).
     */
    public function update(Evento $evento, array $data): Evento
    {
        $map = [
            'name'             => 'nombre',
            'description'      => 'descripcion',
            'longDescription'  => 'longDescription',
            'date'             => 'fecha_inicio',
            'localTime'        => 'localTime',
            'location'         => 'direccion',
            'status'           => 'estado_evento_id',
            'hasDonation'      => 'hasDonation',
            'video'            => 'video_url',
            'image'            => 'imagen_portada_url',
            'colorHex'         => 'color_hex',
            'chronotrackEventId' => 'chronotrack_event_id',
            'deslinde'         => 'deslinde',
            'deslinde_pdf_url' => 'deslinde_pdf_url',
            // Link directo al evento (18/08/2026) — ver elascenso/event,
            // Evento::resolveRouteBinding().
            'url_slug'         => 'url_slug',
            // Ver brain/PLAN-ENDPOINT-CONSUMO-05082026.md — permite corregir
            // desde el panel eventos ya creados que quedaron con el default
            // histórico (tipo_evento_id=1, "Carrera de Ruta") aunque en
            // realidad sean un congreso u otra disciplina.
            'tipo_evento_id'    => 'tipo_evento_id',
            'subtipo_evento_id' => 'subtipo_evento_id',
            'organizador_id'    => 'organizador_id',
            'feePct'            => 'fee_pct',
            // Inscripción en BOB y USD (18/08/2026) — ver
            // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Mapea la
            // clave camelCase del Resource/Request a la columna snake_case.
            'aceptaUsd'        => 'acepta_usd',
            // Congresos con talleres (19/08/2026) — el panel no tenía forma
            // de prender este flag (solo se podía escribir directo en BD);
            // sin él, ResolverPrecioTallerData siempre cobra $0 aunque el
            // taller tenga precio cargado. Mismo patrón que aceptaUsd.
            'talleresConCosto' => 'talleres_con_costo',
            // Cargo de servicio sobre talleres, configurable por evento
            // (19/08/2026) — ver migración add_fee_incluye_talleres_to_eventos_table.
            'feeIncluyeTalleres' => 'fee_incluye_talleres',
            // Precio USD fijo (19/08/2026) — ver brain/PLAN-PRECIO-USD-FIJO-19082026.md.
            'usdPrecioFijo'    => 'usd_precio_fijo',
            // "Pagar en el evento (efectivo)" al agregar un taller a una
            // inscripción pagada — configurable por evento (02/09/2026) —
            // ver migración add_forzar_qr_pago_adicional_to_eventos_table.
            'forzarQrPagoAdicional' => 'forzar_qr_pago_adicional',
            // Purgar datos de Persona/Participante en inscripciones
            // canceladas (01/09/2026) — ver
            // PurgarDatosPersonaCanceladaAction.
            'mantenerDatosPersona' => 'mantener_datos_persona',
            // Orden de secciones en #screen-form-types (25/08/2026) — ver
            // admin-eventos/EventoController::update() y elascenso/event/index.php.
            'seccionesOrden'   => 'secciones_orden',
        ];

        // Columnas NOT NULL sin default en la migración original de `eventos`
        // (longDescription, deslinde, imagen_portada_url, video_url) —
        // UpdateEventosRequest las marca "nullable" porque el panel debe
        // poder vaciarlas, pero un `null` real revienta el UPDATE con un
        // "column cannot be null". `create()` ya coerce esto a '' — acá
        // faltaba el mismo tratamiento. Encontrado el 05/08/2026 al
        // verificar el campo tipo_evento_id nuevo (bug preexistente, sin
        // relación con ese cambio).
        $noNullables = ['longDescription', 'deslinde', 'imagen_portada_url', 'video_url'];

        $attributes = [];
        foreach ($map as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $value = $data[$requestKey];
                if ($value === null && in_array($column, $noNullables, true)) {
                    $value = '';
                }
                $attributes[$column] = $value;
            }
        }

        if (!empty($attributes)) {
            $evento->update($attributes);
        }

        return $this->loadRelations($evento);
    }

    /**
     * Colaborador compartido por App\Actions\CrearEventoAction y por
     * update() acá arriba — único método que sobrevivió como colaborador
     * real después de mover create() a la Action (el resto de los
     * sub-pasos de creación no tenían otro caller, se movieron íntegros).
     */
    public function loadRelations(Evento $evento): Evento
    {
        return $evento->load([
            'coordinates',
            'routes',
            'categories',
            'formTypes.souvenirs',
            'formTypes.formularioCampos.options',
            'promoCodes',
            'auspiciadores',
            'agendaItems',
            'organizador.formasPagoSeleccionadas',
        ]);
    }
}
