<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Liquidación de utilidades de un evento cerrado — registro inmutable una
 * vez creado (no hay flujo de edición/anulación en esta fase). Creada por
 * LiquidarEventoAction, nunca directamente vía Model::create() fuera de
 * ahí, para que `monto_base`/`cantidad_inscripciones` sean siempre un
 * snapshot calculado con la misma lógica.
 */
class Liquidacion extends Model
{
    use HasFactory;

    protected $table = 'liquidaciones';

    protected $fillable = [
        'evento_id',
        'monto_base',
        'cantidad_inscripciones',
        'liquidado_por_admin_user_id',
        'liquidado_en',
        'notas',
    ];

    protected $casts = [
        'monto_base' => 'decimal:2',
        'liquidado_en' => 'datetime',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function liquidadoPor(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'liquidado_por_admin_user_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(LiquidacionDetalle::class);
    }
}
