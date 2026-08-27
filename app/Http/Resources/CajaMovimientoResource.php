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
            'registrationReferencia' => $this->whenLoaded('registration', fn () => $this->registration?->referencia),
            'createdAt'              => optional($this->created_at)->toIso8601String(),
        ];
    }
}
