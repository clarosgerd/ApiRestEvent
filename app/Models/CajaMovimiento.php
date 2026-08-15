<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un movimiento de dinero cobrado en caja — ver
 * PLAN-CAJA-COBRO-PRESENCIAL-14082026.md. Siempre pertenece a un
 * CajaTurno (nunca queda suelto), para que el cierre de turno pueda
 * calcular monto_esperado sumando exactamente lo que corresponde a ese
 * turno.
 */
class CajaMovimiento extends Model
{
    protected $fillable = [
        'caja_turno_id',
        'evento_id',
        'registration_id',
        'admin_user_id',
        'tipo',
        'monto',
        'metodo_pago',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function turno(): BelongsTo
    {
        return $this->belongsTo(CajaTurno::class, 'caja_turno_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }
}
