<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormType extends Model
{
    /** @use HasFactory<\Database\Factories\FormTypeFactory> */
    use HasFactory;
      public $timestamps = false;
     protected $fillable = [
        'event_id',
        'name',
        'icon',
        'description',
        'color',
    ];


    
   public function evento()  {
        return $this->belongsTo('App\Models\Evento','id');
     }

   public function souvenirs()
   {
      return $this->hasMany('App\Models\Souvenir', 'form_types_id');
   }
}
