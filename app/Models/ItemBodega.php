<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bodega de stock por evento — ver PLAN-BODEGA-STOCK-EVENTO-14082026.md.
 * Catálogo de ítems del evento (identidad física: nombre/ícono/foto/
 * requiere_talla/requiere_sexo) — sin precio ni stock propio, eso vive
 * en cada `Souvenir` (la asignación puntual a un form_type).
 */
class ItemBodega extends Model
{
    protected $table = 'item_bodega';

    protected $fillable = [
        'evento_id',
        'nombre',
        'icon',
        'foto_url',
        'requiere_talla',
        'requiere_sexo',
    ];

    protected $casts = [
        'requiere_talla' => 'boolean',
        'requiere_sexo'  => 'boolean',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    /**
     * Las asignaciones a form_types — cada una es un `Souvenir` con su
     * propio price/incluido/item_stock, independiente de las demás
     * (cupos separados por form_type, decisión del usuario).
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(Souvenir::class, 'item_bodega_id');
    }
}
