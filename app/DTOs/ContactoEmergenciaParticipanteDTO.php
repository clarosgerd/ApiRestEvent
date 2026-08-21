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
        // Caja para eventos tipo congreso (20/08/2026) — contacto de
        // emergencia dejó de ser obligatorio incondicionalmente (ver
        // ValidaContactoEmergenciaCondicional), así que $data puede llegar
        // vacío. Defaults a '' preservan las columnas NOT NULL de
        // contacto_emergencia_participantes sin necesitar migración.
        return new self(
            name: $data['nombre'] ?? '',
            phone: $data['celular'] ?? '',
            relationship: $data['relacion'] ?? ''
        );
    }
}