<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    /** @use HasFactory<\Database\Factories\EventosFactory> */
    use HasFactory;
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
        'publicado',
        'destacado',
        
        'contador_visitas',
        'longDescription'
    ];

protected $casts = [
    'hasDonation'  => 'boolean',
    'hasPromoCode' => 'boolean',
    'publicado'    => 'boolean',
];
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
