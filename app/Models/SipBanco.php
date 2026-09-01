<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SIP multi-banco (28/08/2026) — ver
 * brain/api_rest_event/PLAN-SIP-MULTIBANCO-28082026.md. Guarda las
 * credenciales reales de la cuenta SIP (MC4) de un banco, asignada a un
 * organizador. NUNCA se expone por ningún Resource público — solo la
 * consumen los endpoints internos (Concerns/RequiresInternalSecret) y el
 * CRUD de admin-eventos (solo super_admin, y ese tampoco devuelve los
 * campos secretos en las respuestas — ver SipBancoResource).
 */
class SipBanco extends Model
{
    use HasFactory;

    protected $table = 'sip_bancos';

    protected $fillable = [
        'organizador_id',
        'nombre',
        'sip_username',
        'sip_password',
        'sip_apikey',
        'sip_apikey_servicio',
        'sip_base_auth_url',
        'sip_base_api_url',
        'callback_basic_user',
        'callback_basic_password',
        'activo',
    ];

    protected $hidden = [
        'sip_password',
        'sip_apikey',
        'sip_apikey_servicio',
        'callback_basic_password',
    ];

    protected $casts = [
        'organizador_id' => 'integer',
        'activo' => 'boolean',
    ];

    public function organizador(): BelongsTo
    {
        return $this->belongsTo(Organizador::class, 'organizador_id');
    }
}
