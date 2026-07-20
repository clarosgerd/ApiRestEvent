<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organizador extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizadorFactory> */
    use HasFactory;
    protected $table = 'organizadores';
    protected $fillable = [
        'razon_social',
        'nombre_comercial',
        'rut_nit',
        'email',
        'telefono',
        'pais_id',
        'ciudad_id',
        'direccion',
        'logo_url',
        'plan_id',
        'comision_especial',
        'convenio_notas',
        'activo',
    ];

    protected $casts = [
        'comision_especial' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'ciudad_id');
    }

    public function eventos()
    {
        return $this->hasMany(Evento::class, 'organizador_id');
    }
}
