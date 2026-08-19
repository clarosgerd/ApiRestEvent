<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agrupación opcional de sesiones_congreso con modalidad REQUIRED/OPTIONAL
 * y precio base. Las sesiones que no pertenecen a un taller (taller_id null
 * en sesiones_congreso) siguen funcionando como ponencias sueltas para
 * check-in, staff y agenda. Ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
 */
class Taller extends Model
{
    /** @use HasFactory<\Database\Factories\TallerFactory> */
    use HasFactory;

    protected $table = 'talleres';

    protected $fillable = [
        'evento_id',
        'nombre',
        'descripcion',
        'modalidad',
        'precio',
        'orden',
        'activo',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    /**
     * Sesiones que pertenecen a este taller. Importante: solo se cargan
     * sesiones activas por defecto en el flujo del participante — el filtro
     * se aplica donde corresponda (Resource / Action), no acá.
     */
    public function sesiones(): HasMany
    {
        return $this->hasMany(SesionCongreso::class, 'taller_id')
            ->orderBy('fecha')
            ->orderBy('hora_inicio');
    }

    /**
     * Selecciones (pivote) de este taller a través de los participantes
     * — útil para reportes de ocupación por taller.
     */
    public function participanteSesiones(): HasMany
    {
        return $this->hasMany(ParticipanteTallerSesion::class, 'taller_id');
    }
}