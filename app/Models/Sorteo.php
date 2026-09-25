<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SmartStand fase 4 — un sorteo realizado (por una empresa entre sus contactos,
 * o por el organizador entre quienes completaron el pasaporte). Ver
 * RealizarSorteoAction.
 */
class Sorteo extends Model
{
    public const TIPO_EXPOSITOR = 'expositor';
    public const TIPO_PASAPORTE = 'pasaporte';

    protected $table = 'sorteos';

    protected $fillable = [
        'evento_id',
        'tipo',
        'empresa_expositora_id',
        'premio',
        'participante_ganador_id',
        'ganador',
        'candidatos_count',
        'candidatos_hash',
        'filtros',
        'admin_user_id',
        'sorteado_at',
    ];

    protected $casts = [
        'ganador'          => 'array',
        'filtros'          => 'array',
        'candidatos_count' => 'integer',
        'sorteado_at'      => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(EmpresaExpositora::class, 'empresa_expositora_id');
    }
}
