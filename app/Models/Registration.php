<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;


class Registration extends Model
{
    /** @use HasFactory<\Database\Factories\RegistrationFactory> */
    use HasFactory,Notifiable;
     protected $fillable = [
        'referencia',
        'fecha',
        'evento_id',
        'evento_nombre',
        'tipo_pago',
        'pago_status',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function totals(): HasOne
    {
        return $this->hasOne(RegistrationTotal::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participante::class);
    }
}
