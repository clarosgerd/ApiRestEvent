<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Precio por categoría de un combo de talleres (13/10/2026, pedido del
 * usuario) — ver migración `create_taller_combo_precios_table` y
 * App\Models\TallerCombo::precioParaCategoria().
 */
class TallerComboPrecio extends Model
{
    protected $table = 'taller_combo_precios';

    protected $fillable = [
        'taller_combo_id',
        'category_id',
        'precio',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'precio'      => 'float',
    ];

    public function tallerCombo(): BelongsTo
    {
        return $this->belongsTo(TallerCombo::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
