<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resultado extends Model
{
    protected $table = 'resultados';

    protected $fillable = [
        'event_id',
        'participante_id',
        'chip',
        'numero_corredor',
        'numero_documento',
        'tiempo_oficial',
        'tiempo_chip',
        'posicion_general',
        'posicion_categoria',
        'posicion_genero',
        'estado',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'event_id');
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }
}
