<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WhatsApp Business API oficial por organizador (08/10/2026) — ver
 * migración create_whatsapp_cuentas_table. Guarda las credenciales reales
 * de la cuenta de WhatsApp Business (Meta Cloud API) de un organizador.
 * NUNCA se expone `access_token` por ningún Resource — solo lo consumen
 * WhatsappCloudApiService/SendWhatsappOficialMessageJob y el CRUD de
 * admin-eventos (solo super_admin, que tampoco lo devuelve en las
 * respuestas — ver WhatsappCuentaResource), mismo criterio que SipBanco.
 */
class WhatsappCuenta extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_cuentas';

    protected $fillable = [
        'organizador_id',
        'nombre',
        'phone_number_id',
        'business_account_id',
        'access_token',
        'template_name',
        'template_lang',
        'activo',
    ];

    protected $hidden = [
        'access_token',
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
