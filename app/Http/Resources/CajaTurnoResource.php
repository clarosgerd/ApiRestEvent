<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CajaTurnoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'eventoId'       => $this->evento_id,
            'cajeroId'       => $this->admin_user_id,
            'cajeroNombre'   => $this->whenLoaded('cajero', fn () => $this->cajero?->nombre),
            'fondoInicial'   => (float) $this->fondo_inicial,
            'totalCobrado'   => $this->relationLoaded('movimientos') ? (float) $this->movimientos->sum('monto') : null,
            'montoEsperado'  => $this->monto_esperado !== null ? (float) $this->monto_esperado : null,
            'montoContado'   => $this->monto_contado !== null ? (float) $this->monto_contado : null,
            'diferencia'     => $this->diferencia !== null ? (float) $this->diferencia : null,
            'estado'         => $this->estado,
            'notas'          => $this->notas,
            'abiertoAt'      => optional($this->abierto_at)->toIso8601String(),
            'cerradoAt'      => optional($this->cerrado_at)->toIso8601String(),
            // Detalle de cierre de caja (27/08/2026) — solo presente cuando
            // el caller pide explícitamente el detalle (CajaTurnoController::show()),
            // ausente en index() para no inflar la respuesta de la lista.
            'movimientos'    => CajaMovimientoResource::collection($this->whenLoaded('movimientos')),
        ];
    }
}
