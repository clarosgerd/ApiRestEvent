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
        public array $participants

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
            )
        );
    }
}