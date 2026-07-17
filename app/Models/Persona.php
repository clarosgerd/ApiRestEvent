<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Persona extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'personas';

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
        'celular',
        'token',
    ];

    protected $hidden = [
        'password',
        'token',
    ];

    public function contactoEmergencia()
    {
        return $this->hasOne(ContactoEmergencia::class);
    }
}
