<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use app\Models\Participante;

class SouvenirParticipante extends Model
{
    /** @use HasFactory<\Database\Factories\SouverirParticipanteFactory> */
    use HasFactory;
    protected $fillable = [
        'participante_id',
        'souvenir_id',
        'nombre',
        'precio'
    ];

    protected $casts = [

        'precio' => 'decimal:2'

    ];

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }
}
