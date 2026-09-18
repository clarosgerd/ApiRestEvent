<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turno de caja de un cajero — ver PLAN-CAJA-COBRO-PRESENCIAL-14082026.md.
 * Un cajero no puede cobrar sin turno abierto, y no puede tener dos
 * turnos abiertos a la vez (ver CajaTurnoController::abrir()).
 */
class CajaTurno extends Model
{
    protected $fillable = [
        'evento_id',
        'admin_user_id',
        'fondo_inicial',
        'monto_esperado',
        'monto_contado',
        'diferencia',
        'estado',
        'notas',
        'abierto_at',
        'cerrado_at',
    ];

    protected $casts = [
        'fondo_inicial'  => 'decimal:2',
        'monto_esperado' => 'decimal:2',
        'monto_contado'  => 'decimal:2',
        'diferencia'     => 'decimal:2',
        'abierto_at'     => 'datetime',
        'cerrado_at'     => 'datetime',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class);
    }

    public function totalMovimientos(): float
    {
        return (float) $this->movimientos()->sum('monto');
    }

    /**
     * Método de pago en Caja (18/09/2026) — el cierre de turno compara
     * "esperado" contra lo que el cajero cuenta FÍSICAMENTE en el cajón,
     * así que solo el efectivo real entra en esa cuenta (ver
     * CajaTurnoController::cerrar()). El QR nunca pasa por el cajón.
     */
    public function totalEfectivo(): float
    {
        return (float) $this->movimientos()->where('metodo_pago', 'EFECTIVO')->sum('monto');
    }

    public function totalQr(): float
    {
        return (float) $this->movimientos()->where('metodo_pago', 'QR')->sum('monto');
    }
}
