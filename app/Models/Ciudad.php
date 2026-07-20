<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ciudad extends Model
{
    /** @use HasFactory<\Database\Factories\CiudadFactory> */
    use HasFactory;
    protected $table = 'ciudades';
    public $timestamps = false;
    protected $fillable = [
        'pais_id',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }

    public function organizadores()
    {
        return $this->hasMany(Organizador::class, 'ciudad_id');
    }
}
