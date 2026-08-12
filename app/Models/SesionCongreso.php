<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sesión de un congreso (ponencia/taller con sala, horario y cupo) — ver
 * PRD-Agenda-sessiones-onlycongresos.md. Distinta de AgendaItem (ítem de
 * cronograma visual, sin cupo ni asistencia) — `agenda_item_id` es un
 * vínculo opcional, no una relación obligatoria.
 */
class SesionCongreso extends Model
{
    use HasFactory;

    protected $table = 'sesiones_congreso';

    protected $fillable = [
        'evento_id',
        'agenda_item_id',
        'titulo',
        'ponente',
        'ponente_cargo',
        'sala',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'cupo',
        'requiere_inscripcion',
        'activa',
    ];

    protected $casts = [
        'fecha' => 'date',
        'requiere_inscripcion' => 'boolean',
        'activa' => 'boolean',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class, 'agenda_item_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(AsistenciaSesion::class, 'sesion_congreso_id');
    }
}
