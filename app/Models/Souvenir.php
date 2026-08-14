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
    ];

    protected $casts = [
        'incluido'       => 'boolean',
        'requiere_talla' => 'boolean',
        'requiere_sexo'  => 'boolean',
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
