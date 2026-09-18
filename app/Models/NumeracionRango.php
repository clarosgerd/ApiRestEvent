<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aviso de numeración vs. género/edad real en entrega de kit (16/09/2026).
 * Un rango de numeración (bib) por color, ligado a una categoría — ver
 * migración create_numeracion_rangos_table para el porqué de este diseño
 * (independiente de categories.sexo_id/edad_min/edad_max).
 */
class NumeracionRango extends Model
{
    protected $fillable = [
        'category_id',
        'genero_id',
        'edad_min',
        'edad_max',
        'color',
        'numero_min',
        'numero_max',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function genero(): BelongsTo
    {
        return $this->belongsTo(Genero::class);
    }
}
