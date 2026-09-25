<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SmartStand fase 4 — correo que pidió no recibir más seguimientos de
 * expositores (link de baja del correo). Sin updated_at.
 */
class SeguimientoBaja extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'seguimiento_bajas';

    protected $fillable = ['email'];
}
