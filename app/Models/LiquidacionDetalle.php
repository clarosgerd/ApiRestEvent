<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Monto que le corresponde a un socio dentro de una Liquidacion.
 * `socio_nombre`/`porcentaje` son snapshot al momento de liquidar — no se
 * leen del `Socio` actual en tiempo de consulta, justamente para que una
 * edición posterior del socio no reinterprete liquidaciones viejas.
 */
class LiquidacionDetalle extends Model
{
    use HasFactory;

    protected $table = 'liquidacion_detalles';

    protected $fillable = [
        'liquidacion_id',
        'socio_id',
        'socio_nombre',
        'porcentaje',
        'monto',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:2',
        'monto' => 'decimal:2',
    ];

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }
}
