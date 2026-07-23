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
        'promo_code',
        'price',
        'discount_type',
        'discount_percent',
        'status',
        'usado',
        'registration_id',
    ];

    
     public function evento()  {
        return $this->belongsTo('App\Models\Evento','id');
     }
}
