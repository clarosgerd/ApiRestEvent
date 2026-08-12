<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de rubros del presupuesto de un evento (Marketing, Logística,
 * Premios, Patrocinio, Donación...) — cada categoría es de un tipo fijo.
 * Ver PresupuestoEventoController para la validación de que el `tipo` de
 * cada movimiento coincida con el de su categoría.
 */
class PresupuestoCategoria extends Model
{
    use HasFactory;

    protected $table = 'presupuesto_categorias';

    protected $fillable = [
        'nombre',
        'tipo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function movimientos(): HasMany
    {
        return $this->hasMany(PresupuestoEvento::class, 'presupuesto_categoria_id');
    }
}
