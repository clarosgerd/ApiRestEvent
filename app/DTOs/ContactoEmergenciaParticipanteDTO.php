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
        // El contacto de emergencia nunca es obligatorio a nivel de
        // validación (relajado del todo el 31/08/2026, antes dependía de
        // `form_types.requiere_contacto_emergencia`), así que $data puede
        // llegar vacío. Defaults a '' preservan las columnas NOT NULL de
        // contacto_emergencia_participantes sin necesitar migración.
        return new self(
            name: $data['nombre'] ?? '',
            phone: $data['celular'] ?? '',
            relationship: $data['relacion'] ?? ''
        );
    }
}