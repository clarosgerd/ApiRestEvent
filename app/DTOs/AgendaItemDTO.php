<?php

namespace App\DTOs;

class AgendaItemDTO
{
    public function __construct(
        public ?int $formTypeId,
        // Alternativa a formTypeId para creación en un solo request: al crear
        // un evento nuevo, el cliente todavía no conoce los IDs autogenerados
        // de sus formTypes — puede referenciarlos por nombre en su lugar
        // (EventoService::createAgendaItems() lo resuelve tras crear los
        // formTypes). formTypeId gana si ambos vienen (ej. al editar un
        // evento existente, donde sí se conoce el ID real).
        public ?string $formTypeName,
        public ?string $fecha,
        public string $horaInicio,
        public ?string $horaFin,
        public string $titulo,
        public ?string $descripcion,
        public ?string $ponente,
        public ?string $ponenteCargo,
        public ?string $sala,
        public ?string $icono,
        public ?int $orden,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            formTypeId: isset($data['formTypeId']) ? (int) $data['formTypeId'] : (isset($data['form_type_id']) ? (int) $data['form_type_id'] : null),
            formTypeName: $data['formTypeName'] ?? $data['form_type_name'] ?? null,
            fecha: $data['date'] ?? $data['fecha'] ?? null,
            horaInicio: $data['startTime'] ?? $data['hora_inicio'] ?? '',
            horaFin: $data['endTime'] ?? $data['hora_fin'] ?? null,
            titulo: $data['title'] ?? $data['titulo'] ?? '',
            descripcion: $data['description'] ?? $data['descripcion'] ?? null,
            ponente: $data['speaker'] ?? $data['ponente'] ?? null,
            ponenteCargo: $data['speakerRole'] ?? $data['ponente_cargo'] ?? null,
            sala: $data['room'] ?? $data['sala'] ?? null,
            icono: $data['icon'] ?? $data['icono'] ?? null,
            orden: isset($data['orden']) ? (int) $data['orden'] : null,
        );
    }
}
