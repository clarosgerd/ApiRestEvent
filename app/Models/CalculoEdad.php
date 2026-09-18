<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de método de cálculo de edad (16/09/2026) — respalda
 * `categories.calculo_edad_id`. Ver migración create_calculo_edades_table
 * y App\Support\CalculoEdadResolver.
 */
class CalculoEdad extends Model
{
    protected $table = 'calculo_edades';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
