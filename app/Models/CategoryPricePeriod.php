<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Precios por período (12/08/2026) — ver PRD-precios-periodos-fechas.md.
 * Un período de precio de una categoría ("Preventa", "Precio regular",
 * "Último día" — libre, no enum). Ver App\Support\PrecioVigenteData
 * para la regla de qué período/precio aplica hoy.
 */
class CategoryPricePeriod extends Model
{
    protected $table = 'category_price_periods';

    protected $fillable = [
        'category_id',
        'nombre',
        'price',
        // Precio USD fijo por período (20/08/2026) — nullable, ver
        // migración add_price_usd_to_category_price_periods_table.
        'price_usd',
        'fecha_desde',
        'fecha_hasta',
    ];

    protected $casts = [
        'price'       => 'float',
        'price_usd'   => 'float',
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
