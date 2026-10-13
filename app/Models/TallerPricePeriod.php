<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Precios por período para talleres (12/10/2026) — mismo patrón que
 * CategoryPricePeriod, un período de precio de un taller ("Preventa",
 * "Precio regular" — libre, no enum). Ver
 * App\Support\Taller\PrecioVigenteTallerData para la regla de qué
 * período/precio aplica hoy.
 *
 * category_id (13/10/2026, pedido del usuario) — opcional; con categoría,
 * el período solo afecta a esa categoría ("track" exclusivo, ver
 * PrecioVigenteTallerData::paraTaller()); sin categoría (NULL), sigue
 * siendo general como antes.
 */
class TallerPricePeriod extends Model
{
    protected $table = 'taller_price_periods';

    protected $fillable = [
        'taller_id',
        'category_id',
        'nombre',
        'price',
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

    public function taller(): BelongsTo
    {
        return $this->belongsTo(Taller::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
