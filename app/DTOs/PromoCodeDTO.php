<?php

namespace App\DTOs;

class PromoCodeDTO
{
    public function __construct(
        public string $promoCode,
        public float $price,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            promoCode: $data['promo_code'],
            price: (float) $data['price'],
        );
    }
}
