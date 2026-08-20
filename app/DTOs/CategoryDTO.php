<?php

namespace App\DTOs;

class CategoryDTO
{
    public function __construct(
        public string $name,
        public float $price,
        public string $description,
        public ?string $color,
        // Precio USD fijo, sin tipo de cambio (19/08/2026) — ver
        // brain/PLAN-PRECIO-USD-FIJO-19082026.md. Null = esta categoría no
        // tiene precio en USD configurado (comportamiento por default,
        // idéntico a antes de esta feature).
        public ?float $priceUsd = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            price: (float) $data['price'],
            description: $data['description'] ?? '',
            color: $data['color'] ?? null,
            priceUsd: isset($data['price_usd']) && $data['price_usd'] !== '' ? (float) $data['price_usd'] : null,
        );
    }
}
