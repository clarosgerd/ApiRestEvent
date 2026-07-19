<?php

namespace App\DTOs;

class SouvenirFormDTO
{
    public function __construct(
        public string $name,
        public string $icon,
        public float $price,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            icon: $data['icon'] ?? '🎁',
            price: (float) $data['price'],
        );
    }
}
