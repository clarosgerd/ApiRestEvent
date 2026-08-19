<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coordinate extends Model
{
    /** @use HasFactory<\Database\Factories\CoordinateFactory> */
    use HasFactory;
    public $timestamps = false;

    protected $fillable=[
      'event_id',
      'lat',
      'lng',
    ];

    // Fix de precisión lat/lng (19/08/2026) — la columna pasó de FLOAT
    // (precisión simple, perdía dígitos) a DECIMAL(10,6) (exacta), pero
    // PDO devuelve DECIMAL como string. Sin este cast, el JSON de la API
    // mandaba `"lat":"-17.761150"` (string) en vez de un número. `float`
    // acá es un double de 64 bits de PHP — de sobra para 6 decimales,
    // no reintroduce el problema original.
    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];


     public function evento()  {
        return $this->belongsTo('App\Models\Evento','id');
     }  
}
