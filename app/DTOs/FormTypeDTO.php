<?php

namespace App\DTOs;

class FormTypeDTO
{
    public function __construct(
        public string $name,
        public string $icon,
        public string $description,
        public string $tipo,
        public int $cupoTotal,
        public float $precioBase,
        public ?string $color,
        public int $moneda,
        public int $permiteListaEspera,
        public int $hasshirt,
        public int $requiereTalla,
        /** @var SouvenirFormDTO[] */
        public array $souvenirs,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            icon: $data['icon'],
            description: $data['description'] ?? '',
            tipo: $data['tipo'] ?? 'deportivo',
            cupoTotal: (int) $data['cupo_total'],
            precioBase: (float) $data['precio_base'],
            color: $data['color'] ?? null,
            moneda: (int) ($data['moneda'] ?? 1),
            permiteListaEspera: (int) ($data['permite_lista_espera'] ?? 0),
            hasshirt: (int) ($data['hasshirt'] ?? 0),
            requiereTalla: (int) ($data['requiere_talla'] ?? 0),
            souvenirs: array_map(
                fn(array $s) => SouvenirFormDTO::fromArray($s),
                $data['souvenirs'] ?? []
            ),
        );
    }
}
