<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago online del saldo pendiente — autoservicio (22/10/2026). Espejo de
 * PagoAdicionalInscripcion, pero sin payload de edición: al confirmarse
 * (ConfirmarPagoSaldoAction) no se aplica ningún cambio a la inscripción,
 * solo se deja registrations.saldo_pendiente en 0.
 */
class PagoSaldoInscripcion extends Model
{
    protected $table = 'pagos_saldo_inscripcion';

    protected $fillable = [
        'registration_id',
        'referencia',
        'saldo',
        'fee',
        'monto',
        'moneda_pago',
        'qr_id',
        'pay_order_number',
        'pago_status',
        'paid_at',
        'notificado_at',
    ];

    protected $casts = [
        'saldo' => 'decimal:2',
        'fee' => 'decimal:2',
        'monto' => 'decimal:2',
        'paid_at' => 'datetime',
        'notificado_at' => 'datetime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
