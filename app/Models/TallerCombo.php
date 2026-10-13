<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Combo de talleres (12/10/2026, pedido del usuario) — "Taller A + Taller B
 * por el precio de 1" o "por $300 fijo". Sustituye la suma de los precios
 * individuales de sus talleres miembro cuando el participante elige todos
 * juntos en la misma operación — ver App\Support\Taller\ResolverCombosTallerAction.
 *
 * El precio NO vive en este modelo (13/10/2026, pedido del usuario) — el
 * mismo combo puede costar distinto según la categoría del participante,
 * ver `precios()`/`precioParaCategoria()`.
 */
class TallerCombo extends Model
{
    protected $table = 'taller_combos';

    protected $fillable = [
        'evento_id',
        'nombre',
        'fecha_desde',
        'fecha_hasta',
        'activo',
    ];

    protected $casts = [
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'activo'      => 'boolean',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function talleres(): BelongsToMany
    {
        return $this->belongsToMany(Taller::class, 'taller_combo_items');
    }

    public function precios(): HasMany
    {
        return $this->hasMany(TallerComboPrecio::class);
    }

    /**
     * Precio de este combo para la categoría `$categoriaId` (13/10/2026) —
     * gana una fila específica de esa categoría; si no hay, la fila
     * default (`category_id=null`); si no hay ninguna de las dos, `null`
     * (el combo no se ofrece a esa categoría — no hay "precio base" al que
     * caer, a diferencia de un taller o una categoría).
     */
    public function precioParaCategoria(?string $categoriaId): ?float
    {
        if ($categoriaId !== null && ctype_digit($categoriaId)) {
            $especifico = $this->precios->firstWhere('category_id', (int) $categoriaId);
            if ($especifico) {
                return (float) $especifico->precio;
            }
        }

        $default = $this->precios->firstWhere('category_id', null);

        return $default ? (float) $default->precio : null;
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
