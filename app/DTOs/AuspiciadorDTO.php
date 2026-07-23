<?php

namespace App\DTOs;

class AuspiciadorDTO
{
    public function __construct(
        public string $nombre,
        public string $logoUrl,
        public ?string $contacto,
        public ?int $orden,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            nombre: $data['nombre'] ?? $data['name'] ?? '',
            logoUrl: $data['logo_url'] ?? '',
            contacto: $data['contacto'] ?? null,
            orden: isset($data['orden']) ? (int) $data['orden'] : null,
        );
    }
}
