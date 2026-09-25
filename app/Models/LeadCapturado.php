<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SmartStand (25/09/2026) — un lead = una empresa expositora escaneó el
 * gafete de un participante. Único por (empresa, participante): re-escanear
 * actualiza nota/calificación en vez de duplicar.
 */
class LeadCapturado extends Model
{
    protected $table = 'leads_capturados';

    protected $fillable = [
        'empresa_expositora_id',
        'participante_id',
        'nota',
        'calificacion',
        'capturado_at',
        // Fase 4 — estado del correo de seguimiento (ver EnviarSeguimientoLeadAction).
        'seguimiento_estado',
        'seguimiento_motivo',
        'seguimiento_intentos',
        'seguimiento_at',
    ];

    protected $casts = [
        'calificacion'         => 'integer',
        'capturado_at'         => 'datetime',
        'seguimiento_intentos' => 'integer',
        'seguimiento_at'       => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(EmpresaExpositora::class, 'empresa_expositora_id');
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }
}
