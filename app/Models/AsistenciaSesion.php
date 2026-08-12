<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de asistencia de un participante a una sesión de congreso —
 * ver SesionCongreso. `staff` es quién hizo el check-in, guardado
 * explícito en esta fila (a diferencia de `participantes.checked_in_at`,
 * que solo queda en el audit log genérico) porque el PRD pide
 * trazabilidad de qué staff validó el ingreso en cada sesión/sala.
 */
class AsistenciaSesion extends Model
{
    use HasFactory;

    protected $table = 'asistencia_sesion';

    protected $fillable = [
        'sesion_congreso_id',
        'participante_id',
        'checkin_at',
        'staff_admin_user_id',
    ];

    protected $casts = [
        'checkin_at' => 'datetime',
    ];

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(SesionCongreso::class, 'sesion_congreso_id');
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'participante_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'staff_admin_user_id');
    }
}
