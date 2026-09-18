<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sync periódico (pull) de participantes de un evento con registro propio
 * externo (17/09/2026) — una fila = una fuente activa para un evento. Ver
 * App\Services\SyncExternoPullService, que la consume, y
 * App\Http\Controllers\SyncExternoConfigController, que la administra.
 */
class EventoSyncExternoConfig extends Model
{
    protected $table = 'eventos_sync_externo';

    protected $fillable = [
        'evento_id',
        'form_types_id',
        'nombre_fuente',
        'url',
        'token',
        'activo',
        'ultima_sincronizacion_at',
        'ultimo_resultado',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'ultima_sincronizacion_at' => 'datetime',
        'ultimo_resultado' => 'array',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class, 'form_types_id');
    }
}
