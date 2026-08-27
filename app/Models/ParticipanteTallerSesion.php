<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivote que registra la selección de una sesión de congreso (que
 * pertenece a un taller) por parte de un participante. `unit_price`,
 * `discount` y `total` son snapshot financiero — preservan el importe
 * aunque luego cambien los precios del taller o de la sesión. Ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
 */
class ParticipanteTallerSesion extends Model
{
    /** @use HasFactory<\Database\Factories\ParticipanteTallerSesionFactory> */
    use HasFactory;

    protected $table = 'participante_taller_sesion';

    protected $fillable = [
        'participante_id',
        'sesion_congreso_id',
        'taller_id',
        'unit_price',
        'discount',
        'total',
        // Reporte de talleres confiable (27/08/2026) — true cuando el
        // participante eligió "pagar en el evento" al agregar este taller
        // desde su cuenta (autoservicio), y todavía no se cobró. false para
        // talleres de la inscripción original, agregados en Caja (se cobra
        // en efectivo ahí mismo), o confirmados por SIP (ya cobrado
        // online). Ver ActualizarInscripcionPagadaAction::handle().
        'pago_pendiente',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'pago_pendiente' => 'boolean',
    ];

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'participante_id');
    }

    public function sesionCongreso(): BelongsTo
    {
        return $this->belongsTo(SesionCongreso::class, 'sesion_congreso_id');
    }

    public function taller(): BelongsTo
    {
        return $this->belongsTo(Taller::class, 'taller_id');
    }
}