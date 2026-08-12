<?php
namespace App\DTOs;

class SouvenirParticipanteDTO
{
    public function __construct(
       // public int $participante_id,
        public int $souvenir_id,
        public string $name,
        public float $price,
        // Kit/tallas/stock (11/08/2026) — talla/sexo elegidos por el
        // participante para este ítem, null si el ítem no requiere
        // ninguno de los dos. Ver PRD-kit-tallas-stock-lista-espera.md.
        public ?string $talla = null,
        public ?string $sexo = null,

    ){}

    public static function fromArray(array $data): self
    {
        return new self(
           // participante_id: (int) $data['participante_id'],
            souvenir_id: (int) $data['id'],
            name: $data['nombre'],
            price: (float) $data['precio'],
            talla: $data['talla'] ?? null,
            sexo: $data['sexo'] ?? null,
        );
    }
}