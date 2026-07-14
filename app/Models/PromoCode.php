<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    /** @use HasFactory<\Database\Factories\PromoCodeFactory> */
    use HasFactory;
      public $timestamps = false;
     protected $fillable = [
        'event_id',
        'price',
    ];

    
     public function evento()  {
        return $this->belongsTo('App\Models\Evento','id');
     }
}
