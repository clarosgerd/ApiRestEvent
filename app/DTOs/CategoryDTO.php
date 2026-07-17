<?php

namespace App\DTOs;

class CategoryDTO
{
    public function __construct(
        public string $name,
        public float $price,
        public string $description,
        public ?string $color,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            price: (float) $data['price'],
            description: $data['description'] ?? '',
            color: $data['color'] ?? null,
        );
    }
}
