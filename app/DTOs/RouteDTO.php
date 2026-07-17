<?php

namespace App\DTOs;

class RouteDTO
{
    public function __construct(
        public float $lat,
        public float $lng,
        public string $label,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            lat: (float) $data['lat'],
            lng: (float) $data['lng'],
            label: $data['label'] ?? '',
        );
    }
}
