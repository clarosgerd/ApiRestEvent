<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoEvento extends Model
{
    /** @use HasFactory<\Database\Factories\TipoEventoFactory> */
    use HasFactory;
    protected $table = 'tipos_evento';
    public $timestamps = false;
    protected $fillable = [
        'nombre',
        'icono',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function subtipos()
    {
        return $this->hasMany(SubtipoEvento::class, 'tipo_evento_id');
    }
}
