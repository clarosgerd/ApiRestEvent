<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\TotalsResource;
use App\Http\Resources\ParticipanteResource;


class RegistrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       return [

            'referencia' => $this->referencia,
            'fecha' => optional($this->fecha)
                ->format('Y-m-d H:i:s'),
            'evento_id' => (string) $this->evento_id,
            'form_types_id' => $this->form_types_id,
            'evento_nombre' => $this->evento_nombre,
            'tipo_pago' => $this->tipo_pago,
            'pago_status' => $this->pago_status,
            'pay_order_number' => $this->pay_order_number,
            // Inscripción en BOB y USD (18/08/2026) — ver
            // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Snapshot
            // de la moneda de cobro y la tasa usada al confirmar (null en
            // inscripciones legacy BOB).
            'monedaPago' => $this->moneda_pago ?? 'BOB',
            'tipoCambioAplicado' => $this->tipo_cambio_aplicado !== null ? (float) $this->tipo_cambio_aplicado : null,
            'totalPagado' => $this->total_pagado !== null ? (float) $this->total_pagado : null,
            'totales' => new TotalsResource(
                $this->whenLoaded('totals')
            ),
            'participantes' => ParticipanteResource::collection(
                $this->whenLoaded('participants')
            )
        ];
    }
}
