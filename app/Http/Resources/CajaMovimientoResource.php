<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalle de cierre de caja (27/08/2026) — un movimiento individual dentro
 * de un CajaTurno, para la pantalla de drill-down que pidió el usuario
 * ("ver detalle de un turno"). Se expone la referencia de la inscripción
 * (no el id interno) para poder ir del movimiento al comprobante real,
 * mismo criterio que el resto del sistema.
 */
class CajaMovimientoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'tipo'                   => $this->tipo,
            'monto'                  => (float) $this->monto,
            'metodoPago'             => $this->metodo_pago,
            // Quitar/cambiar un taller ya pagado (29/09/2026) — motivo
            // opcional, solo se llena cuando el movimiento incluyó quitar
            // un taller ya cobrado. Ver caja/cierre-detalle.blade.php.
            'motivo'                 => $this->motivo,
            'registrationReferencia' => $this->whenLoaded('registration', fn () => $this->registration?->referencia),
            'createdAt'              => optional($this->created_at)->toIso8601String(),
            // Anular un cobro (02/10/2026) — true si este movimiento es
            // candidato a anularse: no es ya una anulación, tiene monto
            // real (descarta Cortesía, monto 0) y nadie lo anuló antes.
            // Ojo: whenLoaded() no sirve acá — devuelve null (no llama el
            // closure) apenas la relación cargada es null, que es
            // justamente el caso normal (sin anular todavía). Se chequea
            // relationLoaded() directo; sin la relación cargada (no debería
            // pasar, ver controllers) se asume anulable por seguridad en
            // vez de ocultar la acción.
            'anulable'               => $this->tipo !== 'anulacion'
                && (float) $this->monto !== 0.0
                && (! $this->resource->relationLoaded('anulacion') || $this->anulacion === null),
        ];
    }
}
