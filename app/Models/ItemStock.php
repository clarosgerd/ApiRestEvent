<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock por talla×sexo de un ítem del kit (fila de `souvenirs` — ver la
 * decisión de terminología en
 * ApiRestEvent/brain/api_rest_event/PRD-kit-tallas-stock-lista-espera.md:
 * el modelo Souvenir no se renombra, pero todo lo nuevo usa "item").
 *
 * `cantidad_total` es el total cargado, no el disponible — el disponible
 * se calcula contando en vivo desde `SouvenirParticipante` (ver
 * App\Support\DisponibilidadItemData::paraItem()), mismo criterio que
 * `form_types.cupo_total`.
 */
class ItemStock extends Model
{
    protected $table = 'item_stock';

    protected $fillable = [
        'souvenir_id',
        'talla',
        'sexo',
        'cantidad_total',
    ];

    public function souvenir(): BelongsTo
    {
        return $this->belongsTo(Souvenir::class, 'souvenir_id');
    }
}
