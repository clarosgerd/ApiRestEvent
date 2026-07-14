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


     public function evento()  {
        return $this->belongsTo('App\Models\Evento','id');
     }  
}
