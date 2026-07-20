<?php

namespace App\DTOs;

class PromoCodeDTO
{
    public function __construct(
        public string $promoCode,
        public ?float $price,
        public string $discountType,
        public ?float $discountPercent,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            promoCode: $data['promo_code'],
            price: isset($data['price']) ? (float) $data['price'] : null,
            discountType: $data['discount_type'] ?? 'fixed_price',
            discountPercent: isset($data['discount_percent']) ? (float) $data['discount_percent'] : null,
        );
    }
}
