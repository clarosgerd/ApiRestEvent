<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de idempotencia: un certificado de congreso ya enviado a este
 * participante para este evento. Ver EnviarCertificadosCongresoAction —
 * la fila solo se crea si el envío tuvo éxito.
 */
class CertificadoCongresoEnviado extends Model
{
    use HasFactory;

    protected $table = 'certificados_congreso_enviados';

    protected $fillable = [
        'evento_id',
        'participante_id',
        'enviado_at',
    ];

    protected $casts = [
        'enviado_at' => 'datetime',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'participante_id');
    }
}
