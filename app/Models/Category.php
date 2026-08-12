<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;
    public $timestamps = false;

    protected $fillable=[
      'event_id',
      'formulario_id',
      'sexo_id',
      'color',
      'name',
      'price',
      'description',
      'edad_min',
      'edad_max',
      'calculo_edad_id',
    ];


     public function evento()  {
        return $this->belongsTo('App\Models\Evento','event_id');
     }

    // Precios por período (12/08/2026) — ver PRD-precios-periodos-fechas.md.
    public function pricePeriods()
    {
        return $this->hasMany(CategoryPricePeriod::class);
    }
}
