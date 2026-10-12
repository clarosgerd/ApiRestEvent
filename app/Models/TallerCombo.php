<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Combo de talleres (12/10/2026, pedido del usuario) — "Taller A + Taller B
 * por el precio de 1" o "por $300 fijo". `precio_combo` sustituye la suma
 * de los precios individuales de sus talleres miembro cuando el
 * participante elige todos juntos en la misma operación — ver
 * App\Support\Taller\ResolverCombosTallerAction.
 */
class TallerCombo extends Model
{
    protected $table = 'taller_combos';

    protected $fillable = [
        'evento_id',
        'nombre',
        'precio_combo',
        'price_usd_combo',
        'fecha_desde',
        'fecha_hasta',
        'activo',
    ];

    protected $casts = [
        'precio_combo'    => 'float',
        'price_usd_combo' => 'float',
        'fecha_desde'     => 'date',
        'fecha_hasta'     => 'date',
        'activo'          => 'boolean',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function talleres(): BelongsToMany
    {
        return $this->belongsToMany(Taller::class, 'taller_combo_items');
    }

    /**
     * Vigente hoy: activo y, si tiene fechas cargadas, dentro del rango.
     * Sin fechas = vigente indefinidamente mientras esté activo.
     */
    public function vigenteHoy(): bool
    {
        if (! $this->activo) {
            return false;
        }

        $hoy = CarbonImmutable::today();

        if ($this->fecha_desde && $hoy->lt($this->fecha_desde)) {
            return false;
        }

        if ($this->fecha_hasta && $hoy->gt($this->fecha_hasta)) {
            return false;
        }

        return true;
    }
}
