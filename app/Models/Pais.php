<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pais extends Model
{
    /** @use HasFactory<\Database\Factories\PaisFactory> */
    use HasFactory;
    protected $table = 'paises';
    public $timestamps = false;
    protected $fillable = [
        'nombre',
        'iso2',
        'iso3',
        'prefijo_tel',
        'bandera_url',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function ciudades()
    {
        return $this->hasMany(Ciudad::class, 'pais_id');
    }

    public function organizadores()
    {
        return $this->hasMany(Organizador::class, 'pais_id');
    }
}
