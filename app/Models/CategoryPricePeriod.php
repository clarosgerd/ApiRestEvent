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
        'fecha_desde',
        'fecha_hasta',
    ];

    protected $casts = [
        'price'       => 'float',
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
