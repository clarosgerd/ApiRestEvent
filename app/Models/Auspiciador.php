<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auspiciador extends Model
{
    protected $table = 'auspiciadores';
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'nombre',
        'logo_url',
        'contacto',
        'orden',
    ];

    public function evento()
    {
        return $this->belongsTo('App\Models\Evento', 'event_id');
    }
}
