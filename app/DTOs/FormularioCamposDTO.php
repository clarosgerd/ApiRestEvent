<?php

namespace App\DTOs;

class FormularioCamposDTO
{
    public function __construct(
        public string $nombreCampo,
        public ?string $seccion,
        public ?string $etiqueta,
        public string $tipoInput,
        public ?string $placeholder,
        public bool $obligatorio,
        public ?int $orden,
        /** @var QuestionOptionDTO[] */
        public array $options,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            nombreCampo: $data['nombre_campo'],
            seccion: $data['seccion'] ?? null,
            etiqueta: $data['etiqueta'] ?? null,
            tipoInput: $data['tipo_input'] ?? 'text',
            placeholder: $data['placeholder'] ?? null,
            obligatorio: (bool) ($data['obligatorio'] ?? false),
            orden: isset($data['orden']) ? (int) $data['orden'] : null,
            options: array_map(
                fn(array $o) => QuestionOptionDTO::fromArray($o),
                $data['options'] ?? []
            ),
        );
    }
}
