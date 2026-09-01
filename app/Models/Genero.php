<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de género de participante (31/08/2026) — ver
 * PLAN-GENERO-CATALOGO-CAMPOS-OPCIONALES-31082026.md. NO es `Sexo` (esa
 * tabla respalda `categories.sexo_id`, un concepto distinto y sin
 * relación). Este catálogo respalda `participantes.genero`, que sigue
 * siendo un ENUM('Masculino','Femenino','Otro') en base de datos — el
 * `nombre` de cada fila acá tiene que coincidir exacto con uno de esos 3
 * valores o el INSERT en `participantes` falla.
 */
class Genero extends Model
{
    protected $table = 'generos';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
