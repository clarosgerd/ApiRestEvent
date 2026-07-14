<?php
namespace App\DTOs;
class TotalsDTO
{
    public function __construct(
        public float $registration,
        public float $donation,
        public float $souvenirs,
        public float $fee,
        public float $discount,
        public float $grandTotal
    ){}
    public static function fromArray(array $data): self
    {
        return new self(
            registration: (float) $data['inscripcion'],
            donation: (float) $data['donacion'],
            souvenirs: (float) $data['souvenirs'],
            fee: (float) $data['fee'],
            discount: (float) $data['descuento'],
            grandTotal: (float) $data['grand_total']
        );
    }
}