<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipo extends Model
{
    protected $table = 'equipos';

    protected $fillable = [
        'event_id',
        'nombre',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'event_id');
    }

    public function participantes(): HasMany
    {
        return $this->hasMany(Participante::class, 'equipo_id');
    }
}
