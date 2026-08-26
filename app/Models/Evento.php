<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evento extends Model
{
    /** @use HasFactory<\Database\Factories\EventosFactory> */
    use HasFactory, SoftDeletes;
    protected $primaryKey = 'id';
    protected $table = 'eventos';
    protected $fillable = [
      //  'id',
        'organizador_id',
        'tipo_evento_id',
        'subtipo_evento_id',
        'estado_evento_id',
        'pais_id',
        'ciudad_id',
        'nombre',
        'nombre_corto',
        'url_slug',
        'keyword',
        'descripcion',
        'hasDonation',
        'hasPromoCode',
        'reglamento',
        'deslinde',
        'deslinde_pdf_url',
        'fecha_inicio',
        'localTime',
        'fecha_fin',
        'fecha_apertura_inscrip',
        'fecha_cierre_inscrip',
        'mensaje_cierre',
        'lugar',
        'direccion',
        'modalidad', // presencial, virtual, hibrido
        'url_virtual', // URL de la plataforma virtual
        'aforo_total', // Capacidad máxima de asistentes
        'color_id', // Color asociado al evento
        'color_hex', // Color de marca del evento, ej. '#022858' — usado en gafetes/certificados
        'fee_pct', // Cargo de servicio del evento, fracción (0.05 = 5%) — antes hardcodeado, ver PRD-cargo-servicio-por-evento.md
        'chronotrack_event_id', // Id del evento en ChronoTrack, si el organizador ya lo registró ahí (solo lectura de nuestro lado)
        'logo_url', // URL del logo del evento
        'imagen_portada_url', // URL de la imagen de portada del evento
        'icono_url', // URL del icono del evento
        'video_url', // URL del video promocional del evento
        'gpx_url', // URL del archivo GPX para eventos deportivos
      //  'coordinates',  // Latitud geográfica del evento
      //  'route',  // Latitud geográfica del evento
        'link_strava',
        'checkin_tipo',
        'tiene_delivery',
        'tiene_punto_venta',
        'tiene_desafios',
        // Congresos con talleres (18/08/2026) — ver
        // brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md. Si es
        // false, los talleres siguen siendo seleccionables (con cupo /
        // conflicto / obligatorios) pero no suman al grand_total.
        'talleres_con_costo',
        // Cargo de servicio sobre talleres, configurable por evento
        // (19/08/2026) — ver migración add_fee_incluye_talleres_to_eventos_table.
        'fee_incluye_talleres',
        // Inscripción en BOB y USD (18/08/2026) — ver
        // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Bandera por
        // evento que permite al organizador habilitar el pago en USD
        // (extranjeros). Default false: comportamiento BOB-only idéntico
        // al actual.
        'acepta_usd',
        // Precio USD fijo, sin tipo de cambio (19/08/2026) — ver
        // brain/PLAN-PRECIO-USD-FIJO-19082026.md. Modo alternativo a
        // `acepta_usd` con tasa: cuando está prendido, el USD sale de
        // `categories.price_usd` directo, sin tipo_cambio.php. Default
        // false: no cambia nada de lo que ya existe.
        'usd_precio_fijo',
        'publicado',
        'destacado',
        'es_historico', // ETL de datos históricos 2014-hoy, ver elascenso/event/brain/
        'contador_visitas',
        'longDescription',
        // Orden configurable de secciones en la pantalla de tipos de
        // formulario (25/08/2026) — array de las 9 claves fijas
        // (description/calendar/countdown/media/sponsors/kitGallery/
        // routeMap/agenda/formTypes) en el orden elegido por el
        // organizador. null = usa el orden por defecto del frontend, sin
        // cambio de aspecto para eventos que no lo configuraron.
        'secciones_orden',
    ];

protected $casts = [
    'hasDonation'      => 'boolean',
    'hasPromoCode'     => 'boolean',
    'publicado'        => 'boolean',
    'es_historico'     => 'boolean',
    'fee_pct'          => 'float',
    'talleres_con_costo' => 'boolean',
    'fee_incluye_talleres' => 'boolean',
    'acepta_usd'        => 'boolean',
    'usd_precio_fijo'   => 'boolean',
    'secciones_orden'   => 'array',
];
    /**
     * Permite que las rutas `{event}` (GET/PUT/DELETE /event/{event}, y
     * todas las que cuelgan de ella) acepten tanto el id numérico como
     * `url_slug` — ver elascenso/event, pedido 18/08/2026: link directo al
     * evento (?evento=<slug>) legible en vez de solo el id. `url_slug` no
     * tiene índice único todavía (columna libre, sin UI en admin-eventos
     * para editarla hoy) — si dos eventos comparten el mismo valor, se
     * resuelve al primero que encuentre; agregar un índice único cuando
     * `url_slug` se vuelva editable de verdad.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field === null && ctype_digit((string) $value)) {
            return $this->where('id', $value)->first();
        }

        return $this->where($field ?? 'url_slug', $value)->first();
    }

      public function coordinates()
   {
      return $this->hasMany('App\Models\Coordinate', 'event_id');
   }
     public function routes()
   {
      return $this->hasMany('App\Models\Route', 'event_id');
   }
   public function categories()
   {
      return $this->hasMany('App\Models\Category', 'event_id');
   }

    public function formTypes()
   {
      return $this->hasMany('App\Models\FormType', 'event_id');
   }
 
    public function promoCodes()
    {
       return $this->hasMany('App\Models\PromoCode', 'event_id');
    }

    public function auspiciadores()
    {
       return $this->hasMany('App\Models\Auspiciador', 'event_id')->orderBy('orden');
    }

    public function agendaItems()
    {
       return $this->hasMany('App\Models\AgendaItem', 'event_id')
                    ->orderBy('fecha')->orderBy('hora_inicio')->orderBy('orden');
    }

    public function equipos()
    {
       return $this->hasMany('App\Models\Equipo', 'event_id')->orderBy('nombre');
    }

    public function registrations()
    {
       return $this->hasMany('App\Models\Registration', 'evento_id');
    }

    public function liquidacion()
    {
       return $this->hasOne(Liquidacion::class, 'evento_id');
    }

    public function presupuestoMovimientos()
    {
       return $this->hasMany(PresupuestoEvento::class, 'evento_id');
    }

    public function sesionesCongreso()
    {
       return $this->hasMany(SesionCongreso::class, 'evento_id');
    }

    /**
     * Talleres del congreso (sesiones agrupadas con modalidad y precio
     * propio). Ver
     * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
     */
    public function talleres()
    {
        return $this->hasMany(Taller::class, 'evento_id')->orderBy('orden')->orderBy('id');
    }

    public function organizador()
    {
        return $this->belongsTo(Organizador::class, 'organizador_id');
    }

    public function tipoEvento()
    {
        return $this->belongsTo(TipoEvento::class, 'tipo_evento_id');
    }

    public function subtipoEvento()
    {
        return $this->belongsTo(SubtipoEvento::class, 'subtipo_evento_id');
    }

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'ciudad_id');
    }

    }
