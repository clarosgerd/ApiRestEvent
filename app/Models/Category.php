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
      // Precio USD fijo, sin tipo de cambio (19/08/2026) — ver
      // brain/PLAN-PRECIO-USD-FIJO-19082026.md. Nullable: sin este campo
      // cargado, el evento no puede vender esta categoría en USD fijo
      // (CurrencyResolverData::resolverPrecioFijo() rechaza si falta).
      'price_usd',
      'description',
      'edad_min',
      'edad_max',
      'calculo_edad_id',
    ];

    // price_usd (19/08/2026): la columna es DECIMAL, PDO la devuelve como
    // string — sin este cast el JSON mandaría "price_usd":"50.00" en vez
    // de un número. `price` (el campo legado) NO se toca a propósito, ver
    // nota de CategoryResource.
    protected $casts = [
        'price_usd' => 'float',
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
