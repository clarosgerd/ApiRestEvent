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
        // SmartStand (25/09/2026) — inscripción de una empresa expositora.
        'es_expositor',
        'requiere_contacto_emergencia',
        // Ocultar Dirección/Ciudad/Teléfono/Alias por tipo de formulario
        // (01/09/2026) — array de strings entre direccion/ciudad/telefono/alias.
        'campos_ocultos',
        // Edición restringida a solo souvenirs/talleres (04/09/2026) — ver
        // migración add_edicion_solo_extras_to_form_types_table. Con esto en
        // true, el participante no puede tocar sus datos personales ni la
        // categoría al editar su inscripción (pendiente o pagada) — solo
        // puede agregar souvenirs/talleres.
        'edicion_solo_extras',
        // Solo un participante por inscripción (26/09/2026) — ver migración
        // add_un_solo_participante_to_form_types_table.
        'un_solo_participante',
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
        'es_expositor'                  => 'boolean',
        'requiere_contacto_emergencia'  => 'boolean',
        'campos_ocultos'                => 'array',
        'edicion_solo_extras'           => 'boolean',
        'un_solo_participante'          => 'boolean',
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

    /**
     * Staff y ponente/expositor (`es_staff` / `es_ponente`, 26/09/2026): no
     * eligen categoría ni pagan. Distinto de `es_expositor` (empresa que
     * contrata un stand, que sí paga una categoría).
     */
    public function esSinCosto(): bool
    {
        return (bool) ($this->es_staff || $this->es_ponente);
    }

    /**
     * Tipos que no se inscriben como asistentes a talleres/sesiones: la empresa
     * expositora, el staff y el ponente (este último indica qué va a dictar con
     * preguntas adicionales; la vinculación a la sesión la hace el organizador).
     */
    public function sinTalleres(): bool
    {
        return (bool) ($this->es_expositor || $this->esSinCosto());
    }

    /**
     * Staff y ponente no pagan (26/09/2026): además de forzar precios 0 al
     * guardar el tipo (ver `booted()`), una inscripción con total > 0 —por
     * ejemplo por un souvenir con precio o una donación— se rechaza.
     *
     * @throws \DomainException  (el controller la devuelve como 422)
     */
    public function validarSinCosto(float $totalGeneral): void
    {
        if ($this->esSinCosto() && $totalGeneral > 0.01) {
            throw new \DomainException('Este tipo de inscripción es sin costo.');
        }
    }

    /**
     * Staff y ponente son SIEMPRE sin categoría y sin costo: al guardar el tipo
     * se fuerza `requiere_categoria=false`, `precio_base=0` y `costo_edicion=0`,
     * así el formulario, el proxy, la API y los reportes lo ven como un tipo sin
     * categoría y sin cargo sin ninguna rama especial (todos leen esas columnas).
     */
    protected static function booted(): void
    {
        static::saving(function (FormType $formType) {
            if ($formType->esSinCosto()) {
                $formType->requiere_categoria = false;
                $formType->precio_base = 0;
                $formType->costo_edicion = 0;
            }
        });
    }

    /**
     * Reglas de cantidad de participantes y descuento de grupo de este tipo
     * (26/09/2026). Antes solo las aplicaban el front y el proxy de
     * elascenso/event (`_registro_validacion.php`); la API aceptaba cualquier
     * cantidad y cualquier `descuento_registrante`. Las mismas reglas:
     *
     *  - `un_solo_participante`: más de 1 → error (gana sobre lo grupal).
     *  - inscripción grupal activa con `max_integrantes_grupo` = N: más de N →
     *    error, y el descuento de grupo solo existe al llegar a N, como
     *    máximo `inscripcion × descuento_registrante_pct`.
     *  - sin inscripción grupal: sin tope y sin descuento de grupo.
     *
     * El descuento es una COTA SUPERIOR: Caja/POS y la sync externa mandan 0 y
     * siguen pasando; solo se rechaza uno mayor al configurado.
     *
     * @throws \DomainException  (el controller la devuelve como 422)
     */
    public function validarParticipantes(int $participantes, float $inscripcion, float $descuentoGrupal): void
    {
        if ($this->un_solo_participante && $participantes > 1) {
            throw new \DomainException('Este tipo de inscripción admite un solo participante.');
        }

        $max = (int) $this->max_integrantes_grupo;
        $grupal = (bool) $this->permite_inscripcion_grupal && $max > 0;

        if ($grupal && $participantes > $max) {
            throw new \DomainException("Máximo {$max} participantes por inscripción.");
        }

        $permitido = ($grupal && $participantes >= $max)
            ? round($inscripcion * (float) $this->descuento_registrante_pct, 2)
            : 0.0;

        if ($descuentoGrupal > $permitido + 0.02) {
            throw new \DomainException(
                'El descuento de grupo no coincide con el configurado para este tipo de inscripción.'
            );
        }
    }

}
