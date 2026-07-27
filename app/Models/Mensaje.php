<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mensaje extends Model
{
    protected $table = 'mensaje';

    public $timestamps = false;

    protected $fillable = [
        'celular',
        'mensaje',
        'email',
        'tipo',
        'prioridad',
    ];
}
