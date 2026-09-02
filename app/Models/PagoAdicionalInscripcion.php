<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cobro real por SIP del monto adicional al agregar un taller a una
 * inscripción pagada (26/08/2026) — ver
 * PLAN-COBRO-SIP-ADICIONAL-26082026.md. `participantes_payload`/
 * `totales_payload` guardan exactamente lo que se le pasa a
 * ActualizarInscripcionPagadaAction; el taller solo se agrega a la
 * inscripción real cuando `pago_status` pasa a 'paid'
 * (ConfirmarPagoAdicionalAction) — nunca antes.
 */
class PagoAdicionalInscripcion extends Model
{
    protected $table = 'pagos_adicionales_inscripcion';

    protected $fillable = [
        'registration_id',
        'referencia',
        'monto',
        'moneda_pago',
        'participantes_payload',
        'totales_payload',
        'qr_id',
        'pago_status',
        'paid_at',
        // Correo de confirmación por pago adicional (02/09/2026) — ver
        // migración add_notificado_at_to_pagos_adicionales_inscripcion_table.
        'notificado_at',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'participantes_payload' => 'array',
        'totales_payload' => 'array',
        'paid_at' => 'datetime',
        'notificado_at' => 'datetime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
