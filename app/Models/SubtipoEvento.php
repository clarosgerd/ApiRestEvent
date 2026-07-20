<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubtipoEvento extends Model
{
    /** @use HasFactory<\Database\Factories\SubtipoEventoFactory> */
    use HasFactory;
    protected $table = 'subtipos_evento';
    public $timestamps = false;
    protected $fillable = [
        'tipo_evento_id',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function tipoEvento()
    {
        return $this->belongsTo(TipoEvento::class, 'tipo_evento_id');
    }
}
