<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\ContactoEmergenciaParticipante;
use App\Models\SouvenirParticipante;

class Participante extends Model
{
    /** @use HasFactory<\Database\Factories\ParticipanteFactory> */
    use HasFactory;
    protected $table = 'participantes';

    protected $fillable = [
        'registration_id',
        'nombre',
        'apellido',
        'alias',
        'genero',
        'tipo_documento',
        'numero_documento',
        'numero_corredor',
        'chip',
        'polera',
        'precio_polera',
        'fecha_nacimiento',
        'edad',
        'correo',
        'direccion',
        'ciudad',
        'telefono',
        'categoria',
        'equipo_id',
        'quiere_delivery',
        'estado_delivery',
        'delivery_lat',
        'delivery_lng',
        'precio_categoria',
        'donacion',
        'promo_descuento',
        'promo_codigo',
        'subtotal',
        'checked_in_at'
    ];

   protected $casts = [

        'fecha_nacimiento' => 'date',
        'quiere_delivery' => 'boolean',
        'precio_polera' => 'decimal:2',
        'precio_categoria' => 'decimal:2',
        'donacion' => 'decimal:2',
        'promo_descuento' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'checked_in_at' => 'datetime'
    ];
  
 public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
 public function contactoEmergenciaParticipante(): HasOne
    {
        return $this->hasOne(ContactoEmergenciaParticipante::class);
    }

    public function souvenirParticipante(): HasMany
    {
        return $this->hasMany(SouvenirParticipante::class);
    }

     public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function resultado(): HasOne
    {
        return $this->hasOne(Resultado::class);
    }

    /**
     * Sesiones de congreso que este participante apoya como staff/ayudante
     * — solo tiene sentido si su form_type tiene `es_staff=true`, pero la
     * relación en sí no valida eso (la validación vive en el controller al
     * crear la asignación). Ver
     * brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md.
     */
    public function sesionesApoyadas(): BelongsToMany
    {
        return $this->belongsToMany(SesionCongreso::class, 'sesion_congreso_staff', 'participante_id', 'sesion_congreso_id')
            ->wherePivot('rol', 'staff')
            ->withPivot('asignado_por_admin_user_id', 'rol')
            ->withTimestamps();
    }

    /**
     * Sesiones de congreso donde este participante figura como ponente
     * vinculado — solo tiene sentido si su form_type tiene
     * `es_ponente=true`. Ver
     * brain/PLAN-VINCULACION-PONENTES-SESIONES-CONGRESO-13082026.md.
     */
    public function sesionesExpuestas(): BelongsToMany
    {
        return $this->belongsToMany(SesionCongreso::class, 'sesion_congreso_staff', 'participante_id', 'sesion_congreso_id')
            ->wherePivot('rol', 'ponente')
            ->withPivot('asignado_por_admin_user_id', 'rol')
            ->withTimestamps();
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }
}
