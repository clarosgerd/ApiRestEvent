<?php
namespace App\DTOs;
use Carbon\Carbon;

use App\DTOs\TotalsDTO;
use App\DTOs\ParticipantDTO;

class RegistrationDTO
{
    public function __construct(

        public string $reference,
        public Carbon $date,
        public int $eventId,
        public int $formId,
        public string $eventName,
        public string $paymentType,
        public string $paymentStatus,
        public ?string $payOrderNumber,
        public TotalsDTO $totals,
        /** @var ParticipantDTO[] */
        public array $participants,
        // Inscripción en BOB y USD (18/08/2026) — ver
        // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Snapshot de
        // moneda + tasa + total cobrado. Default 'BOB' + nulls para
        // mantener compatibilidad con el camino legacy.
        public ?string $monedaPago = 'BOB',
        public ?float $tipoCambioAplicado = null,
        public ?float $totalPagado = null,

    ){}

    public static function fromArray(array $data): self
    {

   // dd($data);
        return new self(
            reference: $data['referencia'],
            date: Carbon::parse($data['fecha']),
            eventId: (int)$data['evento_id'],
            eventName: $data['evento_nombre'],
            formId: (int)$data['form_types_id'],
            paymentType: $data['tipo_pago'],
            paymentStatus: $data['pago_status'],
            payOrderNumber: $data['pay_order_number'] ?? null,
            totals: TotalsDTO::fromArray($data['totales']),
            participants: array_map(
                fn ($participant) => ParticipantDTO::fromArray($participant),
                $data['participantes']
            ),
            // Inscripción en BOB y USD (18/08/2026) — defaults seguros
            // para no romper callers que no mandan estos campos (legacy).
            monedaPago: isset($data['moneda_pago']) ? strtoupper((string) $data['moneda_pago']) : 'BOB',
            tipoCambioAplicado: isset($data['tipo_cambio_aplicado']) ? (float) $data['tipo_cambio_aplicado'] : null,
            totalPagado: isset($data['total_pagado']) ? (float) $data['total_pagado'] : null,
        );
    }
}