<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable; // Ensure it extends Authenticatable if it handles login
use Laravel\Sanctum\HasApiTokens; // 1. IMPORT THE TRAIT
use Illuminate\Notifications\Notifiable;

class Persona extends Model
{
    /** @use HasFactory<\Database\Factories\PersonaFactory> */
    use HasFactory;
     protected $table = 'personas';
    use HasApiTokens, HasFactory; // 2. USE THE TRAIT INSIDE THE CLASS
    protected $fillable = [
        'email',
        'password',
        'nombre',
        'apellido',
        'alias',
        'sexo',
        'tipo_documento',
        'numero_documento',
        'fecha_nacimiento',
        'correo',
        'direccion',
        'ciudad',
        'telefono',
        'celular','token'
    ];

    
    public function contactoEmergencia()
    {
        return $this->hasOne(ContactoEmergencia::class);
    }
}
