<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de sexo (15/08/2026) — ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md. Respalda
 * `categories.sexo_id` (hoy sin FK, sin uso real) — no relacionado con
 * `participantes.genero`.
 */
class Sexo extends Model
{
    /** @use HasFactory<\Database\Factories\SexoFactory> */
    use HasFactory;

    protected $table = 'sexos';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
