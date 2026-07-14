<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactoEmergenciaParticipante extends Model
{
    /** @use HasFactory<\Database\Factories\ContactoEmergenciaParticipanteFactory> */
    use HasFactory;
     protected $fillable = [
        'participante_id',
        'nombre',
        'celular',
        'relacion'
    ];

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }
}
