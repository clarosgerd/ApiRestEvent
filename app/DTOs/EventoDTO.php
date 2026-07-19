<?php

namespace App\DTOs;

class EventoDTO
{
    public function __construct(
        public string $nombre,
        public string $descripcion,
        public ?string $longDescription,
        public string $fechaInicio,
        public string $localTime,
        public string $direccion,
        public string $status,
        public bool $publicado,
        public bool $hasDonation,
        public ?string $videoUrl,
        public ?string $imagenPortadaUrl,
        /** @var CoordinateDTO[] */
        public array $coordinates,
        /** @var RouteDTO[] */
        public array $routes,
        /** @var CategoryDTO[] */
        public array $categories,
        /** @var FormTypeDTO[] */
        public array $formTypes,
        /** @var PromoCodeDTO[] */
        public array $promoCodes,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            nombre: $data['name'] ?? $data['nombre'] ?? '',
            descripcion: $data['description'] ?? $data['descripcion'] ?? '',
            longDescription: $data['longDescription'] ?? null,
            fechaInicio: $data['date'] ?? $data['fecha_inicio'] ?? now()->toDateTimeString(),
            localTime: $data['localTime'] ?? '',
            direccion: $data['location'] ?? $data['direccion'] ?? '',
            status: $data['status'] ?? 'open',
            publicado: (bool) ($data['publicado'] ?? true),
            hasDonation: (bool) ($data['hasDonation'] ?? false),
            videoUrl: $data['video'] ?? $data['video_url'] ?? null,
            imagenPortadaUrl: $data['image'] ?? $data['imagen_portada_url'] ?? null,
            coordinates: array_map(
                fn(array $c) => CoordinateDTO::fromArray($c),
                $data['coordinates'] ?? []
            ),
            routes: array_map(
                fn(array $r) => RouteDTO::fromArray($r),
                $data['route'] ?? $data['routes'] ?? []
            ),
            categories: array_map(
                fn(array $c) => CategoryDTO::fromArray($c),
                $data['categories'] ?? []
            ),
            formTypes: array_map(
                fn(array $f) => FormTypeDTO::fromArray($f),
                $data['formTypes'] ?? []
            ),
            promoCodes: array_map(
                fn(array $p) => PromoCodeDTO::fromArray($p),
                $data['promoCodes'] ?? []
            ),
        );
    }
}
