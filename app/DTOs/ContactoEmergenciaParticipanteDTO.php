<?php

namespace App\DTOs;

class ContactoEmergenciaParticipanteDTO
{
    public function __construct(

        public string $name,
        public string $phone,
        public string $relationship

    ){}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['nombre'],
            phone: $data['celular'],
            relationship: $data['relacion']
        );
    }
}