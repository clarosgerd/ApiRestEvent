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

    /**
     * Valor que guarda una inscripción cuando su form_type oculta el género
     * (`campos_ocultos` con 'genero', ej. empresa expositora). Es uno de los 3
     * valores del ENUM de `participantes.genero`.
     */
    public const NEUTRO = 'Otro';

    /**
     * Nombres aceptados al inscribir/editar: los activos del catálogo MÁS el
     * neutro. Desactivar "Otro" en el catálogo solo lo saca de la lista que ve
     * el participante; no debe romper la inscripción de un form_type que oculta
     * el género (bug real 25/09/2026: "Otro" inactivo → 422 al confirmar).
     *
     * @return list<string>
     */
    public static function nombresAceptados(): array
    {
        return static::where('activo', true)->pluck('nombre')->push(self::NEUTRO)->unique()->values()->all();
    }
}
