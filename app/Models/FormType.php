<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormType extends Model
{
    /** @use HasFactory<\Database\Factories\FormTypeFactory> */
    use HasFactory;
      public $timestamps = false;
     protected $fillable = [
        'event_id',
        'name',
        'icon',
        // Tarjeta de tipo de formulario simplificada (19/08/2026) — imagen
        // opcional; si está vacía, el frontend cae al emoji de `icon`.
        'imagen_url',
        'description',
        'tipo',
        'cupo_total',
        'precio_base',
        'color',
        'moneda',
        'activo',
        'permite_lista_espera',
        'requiere_categoria',
        'requiere_talla',
        'requiere_distancia',
        'hasshirt',
        'costo_polera',
        'hasQuestion',
        'permite_inscripcion_grupal',
        'has_team',
        'has_delivery',
        'has_donation',
        'has_promo_code',
        'es_staff',
        'es_ponente',
        'requiere_contacto_emergencia',
        // Ocultar Dirección/Ciudad/Teléfono/Alias por tipo de formulario
        // (01/09/2026) — array de strings entre direccion/ciudad/telefono/alias.
        'campos_ocultos',
        'max_integrantes_grupo',
        'descuento_registrante_pct',
        'hasQuestion',
        'costo_edicion',
        'tiempo_expiracion_min',
        'texto_boton',
    ];

    // No existía ningún $casts en este modelo antes de este cambio — se
    // agrega acotado a los 2 campos nuevos (booleans reales en la BD desde
    // la migración 2026_08_10_140000) sin tocar el comportamiento de los
    // demás campos, que seguían funcionando sin casts.
    protected $casts = [
        'has_donation'                  => 'boolean',
        'has_promo_code'                => 'boolean',
        'es_staff'                      => 'boolean',
        'es_ponente'                    => 'boolean',
        'requiere_contacto_emergencia'  => 'boolean',
        'campos_ocultos'                => 'array',
        // Bug real (19/08/2026) — mismo patrón ya visto antes con
        // evento_id (memoria: "403 falso solo en UAT por driver PDO
        // devolviendo string"): sin cast, PDO en el hosting real devuelve
        // esta columna como string, y Carbon 3.x rechaza un string en
        // addMinutes() ("rawAddUnit(): Argument #3 must be of type
        // int|float, string given") — ver ExpirarInscripcionesPendientesAction,
        // que corre por cron y tumbaba el comando entero.
        'tiempo_expiracion_min' => 'integer',
    ];


    
   public function evento()  {
        return $this->belongsTo('App\Models\Evento','id');
     }

   public function souvenirs()
   {
      return $this->hasMany('App\Models\Souvenir', 'form_types_id');
   }

    public function formularioCampos()
   {
      return $this->hasMany('App\Models\FormularioCampos', 'form_types_id');
   }

    /**
     * Cuenta los participantes de inscripciones vigentes (ni canceladas
     * ni fallidas) de este form_type — mismo filtro que usa
     * RegistrationService::deactivateFormTypeIfCupoLleno(), factorizado
     * acá para que FormTypeResource (cupoDisponible expuesto por API,
     * ver PRD-kit-tallas-stock-lista-espera.md) y el propio Service usen
     * la misma cuenta.
     */
    public function inscritosVigentes(): int
    {
        return Participante::whereHas('registration', function ($query) {
            $query->where('form_types_id', $this->id)
                ->whereNotIn('pago_status', ['cancelled', 'failed']);
        })->count();
    }

    public function cupoDisponible(): int
    {
        return max(0, $this->cupo_total - $this->inscritosVigentes());
    }

}
