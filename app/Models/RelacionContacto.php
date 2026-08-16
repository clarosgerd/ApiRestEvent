<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de relación del contacto de emergencia (15/08/2026) — ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md. Aditivo — no
 * relacionado como FK con `contacto_emergencia_participantes.relacion`
 * (que sigue siendo texto libre) en esta sesión.
 */
class RelacionContacto extends Model
{
    /** @use HasFactory<\Database\Factories\RelacionContactoFactory> */
    use HasFactory;

    protected $table = 'relaciones_contacto';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
