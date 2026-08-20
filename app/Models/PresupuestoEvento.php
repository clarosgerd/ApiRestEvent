<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento manual de ingreso/gasto del presupuesto de un evento — ver
 * PresupuestoCategoria y elascenso/event/brain/ (sesión 11/08/2026). No
 * incluye las inscripciones capturadas por la plataforma (eso se calcula
 * en BalanceEventoData a partir de `registration_totals`, no se duplica
 * acá).
 */
class PresupuestoEvento extends Model
{
    use HasFactory;

    protected $table = 'presupuesto_evento';

    protected $fillable = [
        'evento_id',
        'presupuesto_categoria_id',
        'tipo',
        'monto',
        'moneda',
        'fecha',
        'comprobante_url',
        'admin_user_id',
    ];

    protected $casts = [
        'evento_id' => 'integer',
        'monto' => 'decimal:2',
        'fecha' => 'date',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(PresupuestoCategoria::class, 'presupuesto_categoria_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }
}
