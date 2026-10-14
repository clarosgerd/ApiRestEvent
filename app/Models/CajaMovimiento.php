<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        // Quitar/cambiar un taller ya pagado (29/09/2026) — exigido por
        // ActualizarInscripcionPagadaAction cuando el movimiento incluye
        // quitar un taller ya cobrado. Ver Caja controller.
        'motivo',
        // Observaciones (07/10/2026) — nota libre y opcional, disponible
        // para cualquier forma de pago (a diferencia de `motivo`, que es
        // específico y obligatorio solo al quitar un taller pagado).
        'observaciones',
        // Anular un cobro (02/10/2026) — en el movimiento tipo='anulacion',
        // apunta al movimiento original que revierte. Ver AnularCobroAction.
        'anula_movimiento_id',
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

    /**
     * Solo en un movimiento tipo='anulacion': el movimiento original que
     * revierte.
     */
    public function movimientoAnulado(): BelongsTo
    {
        return $this->belongsTo(self::class, 'anula_movimiento_id');
    }

    /**
     * Inverso — si existe, es la anulación que revirtió ESTE movimiento.
     */
    public function anulacion(): HasOne
    {
        return $this->hasOne(self::class, 'anula_movimiento_id');
    }
}
