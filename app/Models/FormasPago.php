<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormasPago extends Model
{
    /** @use HasFactory<\Database\Factories\FormasPagoFactory> */
    use HasFactory;

    protected $table = 'formas_pagos';

    protected $fillable = [
        'slug',
        'nombre',
        'descripcion',
        'pasarela',
        'tipo',
        'organizador_id',
        'config',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'config' => 'array',
    ];

    /**
     * NULL cuando es un método del sistema disponible para cualquier
     * organizador (sip, multipago, pendiente). Con valor cuando es un
     * método propio de ese organizador (su convenio o sus instrucciones).
     */
    public function organizador()
    {
        return $this->belongsTo(Organizador::class, 'organizador_id');
    }

    public function organizadoresQueLoSeleccionaron()
    {
        return $this->belongsToMany(Organizador::class, 'organizador_formas_pago', 'forma_pago_id', 'organizador_id')
            ->withPivot('activo')
            ->withTimestamps();
    }
}
