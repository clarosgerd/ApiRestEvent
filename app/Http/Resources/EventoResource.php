<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class EventoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public $preserveKeys = true;
    public function toArray(Request $request): array
    {
      //  dd($this->resource);
     return [
            'id'                        =>$this->id,
            // CRUD de organizadores (11/08/2026) — antes solo se guardaba
            // organizador_id sin exponerlo, todo evento nuevo quedaba
            // pegado al id=1 por default sin que el panel pudiera verlo ni
            // cambiarlo. Ver OrganizadorController /
            // PRD-organizadores-crud.md. `organizador` (objeto) solo viene
            // si se hizo eager-load de la relación (whenLoaded).
            'organizadorId'             =>$this->organizador_id,
            'organizador'               =>$this->whenLoaded('organizador', fn() => $this->organizador ? [
                'id'     => $this->organizador->id,
                'nombre' => $this->organizador->nombre_comercial ?: $this->organizador->razon_social,
            ] : null),
            // Ver brain/PLAN-ENDPOINT-CONSUMO-05082026.md — antes hardcodeado
            // en 1 para todo evento, ya conectado de verdad.
            'tipoEventoId'              =>$this->tipo_evento_id,
            'tipoEvento'                =>$this->whenLoaded('tipoEvento', fn() => $this->tipoEvento?->nombre),
            'subtipoEventoId'           =>$this->subtipo_evento_id,
            'subtipoEvento'             =>$this->whenLoaded('subtipoEvento', fn() => $this->subtipoEvento?->nombre),
     //       'status'                    =>$this->estado_evento_id,
    //        'pais_id'                   =>$this->pais_id,
    //        'ciudad_id'                 =>$this->ciudad_id,
            'name'                      =>$this->nombre,
            'date'                      =>$this->fecha_inicio,
            'localTime'                 =>Carbon::parse($this->localTime)->format('H:i:s'),
            'location'                  =>$this->direccion, 
            'coordinates'               =>CoordinateResource::collection($this->whenLoaded('coordinates')),  // Latitud geográfica del evento
            'route'                     =>RouteResource::collection($this->whenLoaded('routes')),  // Ruta del evento
            //new UserResource($this->whenLoaded('author')),
            'status'                    =>$this->estado_evento_id,
     //       'nombre_corto'              =>$this->nombre_corto,
            // Link directo al evento (18/08/2026) — ver elascenso/event,
            // Evento::resolveRouteBinding(). Null/'' si el organizador
            // nunca lo seteó (sin UI en admin-eventos todavía); el
            // frontend cae a `id` para armar el link para compartir.
            'urlSlug'                   =>$this->url_slug ?: null,
     //       'keyword'                   =>$this->keyword,
            'image'                     =>$this->imagen_portada_url,
            'colorHex'                  =>$this->color_hex,
            // Cargo de servicio (11/08/2026) — fracción, 0.05 = 5%. Antes
            // hardcodeado en 4 lugares del lado de elascenso/event, ver
            // PRD-cargo-servicio-por-evento.md.
            'fee_pct'                   =>(float) $this->fee_pct,
            'chronotrackEventId'        =>$this->chronotrack_event_id,
            'video'                     =>$this->video_url,
            'description'               =>$this->descripcion,
            'longDescription'           =>$this->longDescription,
            'hasDonation'                 =>$this->hasDonation,
            'hasPromoCode'                 =>$this->hasPromoCode,
            
            'categories'                =>CategoryResource::collection($this->whenLoaded('categories')),  // Categorías del evento
            'formTypes'                 =>FormTypeResource::collection($this->whenLoaded('formTypes')),  // Tipos de formulario del evento
            // Métodos de pago habilitados para este evento (los del sistema
            // y/o los propios del organizador — ver Organizador::formasPagoEfectivas()).
            // Pago pendiente USD (24/08/2026) — "pendiente_usd" se excluye salvo
            // que el evento sea usdPrecioFijo Y el organizador tenga un link
            // cargado (ver Organizador::linkPagoPendienteUsd()); si no, un
            // participante podría elegirlo sin que exista link para enviarle.
            'formasPago'                =>$this->relationLoaded('organizador') && $this->organizador
                                                ? FormasPagoResource::collection(
                                                    $this->organizador->formasPagoEfectivas()->reject(
                                                        fn ($fp) => $fp->slug === 'pendiente_usd'
                                                            && (!$this->usd_precio_fijo || !filled($this->organizador->linkPagoPendienteUsd()))
                                                    )
                                                )
                                                : [],
                 //     'reglamento'                =>$this->reglamento,
            'deslinde'                  =>$this->deslinde,
            'deslinde_pdf_url'          =>$this->deslinde_pdf_url,
       //     'fecha_fin'                 =>$this->fecha_fin,
       //     'fecha_apertura_inscrip'    =>$this->fecha_apertura_inscrip,
       //     'fecha_cierre_inscrip'      =>$this->fecha_cierre_inscrip,
       //     'mensaje_cierre'            =>$this->mensaje_cierre,
       //     'lugar'                     =>$this->lugar,
       //     'modalidad'                 =>$this->modalidad,
       //     'url_virtual'               =>$this->url_virtual, 
       //     'aforo_total'               =>$this->aforo_total,
       //     'color_id'                  =>$this->color_id, 
       //     'logo_url'                  =>$this->logo_url,
       //     'icono_url'                 =>$this->icono_url,
       //     'gpx_url'                   =>$this->gpx_url,
       //     'link_strava'               =>$this->link_strava,
       //     'checkin_tipo'              =>$this->checkin_tipo,
       //     'tiene_delivery'            =>$this->tiene_delivery,
       //     'tiene_punto_venta'         =>$this->tiene_punto_venta,
       //     'tiene_desafios'            =>$this->tiene_desafios,
            'publicado'                 =>$this->publicado,
       //     'destacado'                 =>$this->destacado,
       //   'hasDonation'                 =>$this->hasDonation,
            'promoCodes'                 =>PromoCodeResource::collection($this->whenLoaded('promoCodes')),  // Códigos promocionales del evento
            'auspiciadores'              =>AuspiciadorResource::collection($this->whenLoaded('auspiciadores')),  // Auspiciadores del evento (carrusel de logos)
            'agenda'                     =>AgendaItemResource::collection($this->whenLoaded('agendaItems')),  // Agenda del evento (sesiones/ponentes/salas o cronograma del día)
            // Congresos con talleres (18/08/2026) — ver
            // brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
            // Solo se carga cuando el eager-load lo pide (talleres.sesiones)
            // para no inflar el payload público con datos admin. Cada taller
            // carga sus sesiones activas con cupo/ocupados.
            'talleres'                   =>TallerResource::collection($this->whenLoaded('talleres')),
            'talleresConCosto'           =>(bool) $this->talleres_con_costo,
            // Cargo de servicio sobre talleres, configurable por evento
            // (19/08/2026) — si es false, el fee vuelve a calcularse solo
            // sobre inscripción (talleres quedan afuera de esa base, igual
            // que souvenirs/donación). Ver
            // CrearInscripcionAction::validateFeePct() y
            // elascenso/event/api/_registro_validacion.php.
            'feeIncluyeTalleres'         =>(bool) $this->fee_incluye_talleres,
            // Inscripción en BOB y USD (18/08/2026) — ver
            // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Si es
            // false, el frontend del participante oculta el selector de
            // moneda y fuerza BOB (comportamiento legacy).
            'aceptaUsd'                  =>(bool) $this->acepta_usd,
            // Precio USD fijo, sin tipo de cambio (19/08/2026) — ver
            // brain/PLAN-PRECIO-USD-FIJO-19082026.md. Modo alternativo a
            // `aceptaUsd` con tasa: cuando está prendido, el frontend usa
            // `category.priceUsd` directo en vez de tipo_cambio.php.
            'usdPrecioFijo'              =>(bool) $this->usd_precio_fijo,
            'equipos'                    =>EquipoResource::collection($this->whenLoaded('equipos')),  // Catálogo de equipos (precargado por el organizador) para form_types con hasTeam
    //        'contador_visitas'          =>$this->contador_visitas
    ];
    
    }
}
