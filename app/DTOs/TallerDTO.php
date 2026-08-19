<?php
namespace App\DTOs;

class TallerDTO
{
    public function __construct(
        public int $id,
        public int $eventoId,
        public string $nombre,
        public ?string $descripcion,
        public string $modalidad,
        public ?float $precio,
        public int $orden,
        public bool $activo,
    ){}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            eventoId: (int) $data['evento_id'],
            nombre: $data['nombre'],
            descripcion: $data['descripcion'] ?? null,
            modalidad: $data['modalidad'],
            precio: isset($data['precio']) ? (float) $data['precio'] : null,
            orden: (int) ($data['orden'] ?? 0),
            activo: (bool) ($data['activo'] ?? true),
        );
    }
}