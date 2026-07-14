<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactoEmergencia extends Model
{
    /** @use HasFactory<\Database\Factories\ContactoEmergenciaFactory> */
    use HasFactory;
    protected $table = 'contactos_emergencia';

    protected $fillable = [
        'persona_id',
        'nombre',
        'celular',
        'relacion'
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }
    
}
