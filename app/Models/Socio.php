<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Socio de PassToGo con un % de reparto sobre el service fee cobrado en
 * cada evento liquidado. Ver LiquidarEventoAction — la validación de que
 * los `porcentaje` de los socios activos sumen 100 vive ahí, no acá.
 */
class Socio extends Model
{
    use HasFactory;

    protected $table = 'socios';

    protected $fillable = [
        'nombre',
        'porcentaje',
        'activo',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function liquidacionDetalles(): HasMany
    {
        return $this->hasMany(LiquidacionDetalle::class, 'socio_id');
    }
}
