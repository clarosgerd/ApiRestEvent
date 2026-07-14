<?php
namespace App\DTOs;

class SouvenirParticipanteDTO
{
    public function __construct(
       // public int $participante_id,
        public int $souvenir_id,
        public string $name,
        public float $price

    ){}

    public static function fromArray(array $data): self
    {
        return new self(
           // participante_id: (int) $data['participante_id'],
            souvenir_id: (int) $data['id'],
            name: $data['nombre'],
            price: (float) $data['precio']
        );
    }
}