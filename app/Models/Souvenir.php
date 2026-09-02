<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Souvenir extends Model
{
    /** @use HasFactory<\Database\Factories\SouvenirFactory> */
    use HasFactory;
  public $timestamps = false;
     protected $fillable = [
        'form_types_id',
        'item_bodega_id',
        'name',
        'icon',
        'price',
        'incluido',
        'foto_url',
        'requiere_talla',
        'requiere_sexo',
        // Souvenirs invisibles para el participante (22/08/2026) — ver
        // migración add_visible_participante_to_souvenirs_table.
        'visible_participante',
        // Cargo de servicio por souvenir individual (01/09/2026) — ver
        // migración add_aplica_cargo_servicio_to_souvenirs_table.
        'aplica_cargo_servicio',
        // Texto promocional por souvenir (02/09/2026) — ver migración
        // add_texto_promocional_to_souvenirs_table. Puramente de
        // marketing, no afecta precio ni disponibilidad.
        'texto_promocional',
    ];

    protected $casts = [
        'incluido'               => 'boolean',
        'requiere_talla'         => 'boolean',
        'requiere_sexo'          => 'boolean',
        'visible_participante'   => 'boolean',
        'aplica_cargo_servicio'  => 'boolean',
    ];


     public function formType()  {
        return $this->belongsTo('App\Models\FormType','form_types_id','id');
     }

    public function stock()
    {
        return $this->hasMany(ItemStock::class, 'souvenir_id');
    }

    /**
     * Ítem de bodega del que salió esta asignación (nullable — ver
     * PLAN-BODEGA-STOCK-EVENTO-14082026.md; un Souvenir sigue pudiendo
     * ser standalone, sin vínculo a ningún catálogo, igual que siempre).
     */
    public function itemBodega()
    {
        return $this->belongsTo(ItemBodega::class, 'item_bodega_id');
    }

}
