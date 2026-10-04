<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_user_id',
        // App de staff offline (02/10/2026) — autor del check-in cuando lo
        // hizo una Persona (app móvil), no un AdminUser (panel). Ver
        // AdminAuditLogger::log().
        'persona_id',
        'accion',
        'entidad',
        'entidad_id',
        'evento_id',
        'datos_antes',
        'datos_despues',
    ];

    protected $casts = [
        'datos_antes'   => 'array',
        'datos_despues' => 'array',
        'created_at'    => 'datetime',
    ];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    public function personaAutor(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }
}
