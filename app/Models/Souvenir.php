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
        'name',
        'icon',
        'price',
     
    ];

    
     public function formType()  {
        return $this->belongsTo('App\Models\FormType','form_types_id','id');
     }

}
