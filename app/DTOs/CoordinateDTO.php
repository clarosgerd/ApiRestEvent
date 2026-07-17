<?php

namespace App\DTOs;

class CoordinateDTO
{
    public function __construct(
        public float $lat,
        public float $lng,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            lat: (float) $data['lat'],
            lng: (float) $data['lng'],
        );
    }
}
