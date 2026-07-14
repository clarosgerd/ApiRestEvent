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


     public function evento()  {
        return $this->belongsTo('App\Models\Evento','id');
     }  
}
