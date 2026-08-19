<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    /** @use HasFactory<\Database\Factories\RouteFactory> */
    use HasFactory;
     public $timestamps = false;
    protected $fillable=[
      'event_id',
      'lat',
      'lng',
      'label',
    ];

    // Fix de precisión lat/lng (19/08/2026) — ver Coordinate::$casts, mismo
    // motivo: la columna pasó a DECIMAL(10,6) (PDO la devuelve como
    // string) y el JSON de la API necesita seguir mandando un número.
    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];


     public function evento()  {
        return $this->belongsTo('App\Models\Evento','id');
     }  
}
