<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'precio_categoria',
        'donacion',
        'promo_descuento',
        'promo_codigo',
        'subtotal'
    ];

   protected $casts = [

        'fecha_nacimiento' => 'date',
        'quiere_delivery' => 'boolean',
        'precio_polera' => 'decimal:2',
        'precio_categoria' => 'decimal:2',
        'donacion' => 'decimal:2',
        'promo_descuento' => 'decimal:2',
        'subtotal' => 'decimal:2'
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

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }
}
