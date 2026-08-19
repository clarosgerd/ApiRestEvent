<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'taller_id',
        'titulo',
        'ponente',
        'ponente_cargo',
        'sala',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'cupo',
        'precio',
        'requiere_inscripcion',
        'activa',
    ];

    protected $casts = [
        'fecha' => 'date',
        'precio' => 'decimal:2',
        'requiere_inscripcion' => 'boolean',
        'activa' => 'boolean',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    /**
     * Taller al que pertenece esta sesión. Si es null, la sesión es una
     * ponencia suelta (no seleccionable en la inscripción, solo visible
     * en agenda / check-in). Ver
     * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
     */
    public function taller(): BelongsTo
    {
        return $this->belongsTo(Taller::class, 'taller_id');
    }

    /**
     * Selecciones (pivote) de esta sesión — útil para cupos y reportes.
     */
    public function participanteSesiones(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ParticipanteTallerSesion::class, 'sesion_congreso_id');
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class, 'agenda_item_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(AsistenciaSesion::class, 'sesion_congreso_id');
    }

    /**
     * Participantes (form_type con `es_staff=true`) asignados por el
     * organizador para apoyar esta sesión — ver
     * brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md. Distinto
     * de `asistencias()`: eso es quién ASISTIÓ (check-in), esto es quién
     * fue ASIGNADO a dar soporte (decisión del organizador, no requiere
     * check-in). Filtrado por `rol=staff` en la tabla pivote — desde
     * 13/08/2026 esa misma tabla también guarda ponentes, ver
     * `ponentesVinculados()`.
     */
    public function staffAsignado(): BelongsToMany
    {
        return $this->belongsToMany(Participante::class, 'sesion_congreso_staff', 'sesion_congreso_id', 'participante_id')
            ->wherePivot('rol', 'staff')
            ->withPivot('asignado_por_admin_user_id', 'rol')
            ->withTimestamps();
    }

    /**
     * Participantes (form_type con `es_ponente=true`) vinculados como
     * expositores de esta sesión — ver
     * brain/PLAN-VINCULACION-PONENTES-SESIONES-CONGRESO-13082026.md.
     * Complementa (no reemplaza) el campo de texto libre `ponente`/
     * `ponente_cargo`, que sigue existiendo para expositores invitados
     * que nunca se registraron en el sistema.
     */
    public function ponentesVinculados(): BelongsToMany
    {
        return $this->belongsToMany(Participante::class, 'sesion_congreso_staff', 'sesion_congreso_id', 'participante_id')
            ->wherePivot('rol', 'ponente')
            ->withPivot('asignado_por_admin_user_id', 'rol')
            ->withTimestamps();
    }
}
