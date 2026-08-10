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
        public float $costoEdicion,
        public int $tiempoExpiracionMin,
        public string $color,
        public int $moneda,
        public int $permiteListaEspera,
        public int $hasshirt,
        public float $costoPolera,
        public int $hasQuestion,
        public int $requiereTalla,
        public bool $permiteInscripcionGrupal,
        public int $maxIntegrantesGrupo,
        public float $descuentoRegistrantePct,
        public bool $hasTeam,
        public bool $hasDelivery,
        public bool $hasDonation,
        public bool $hasPromoCode,
        public bool $requiereCategoria,
        /** @var SouvenirFormDTO[] */
        public array $souvenirs,
        /** @var FormularioCamposDTO[] */
        public array $preguntas,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            icon: $data['icon'] ?? '🏃',
            description: $data['description'] ?? '',
            tipo: $data['tipo'] ?? 'deportivo',
            cupoTotal: (int) $data['cupo_total'],
            precioBase: (float) $data['precio_base'],
            costoEdicion: (float) ($data['costo_edicion'] ?? 0),
            tiempoExpiracionMin: (int) ($data['tiempo_expiracion_min'] ?? 30),
            color: $data['color'] ?? '#00bad2',
            moneda: (int) ($data['moneda'] ?? 1),
            permiteListaEspera: (int) ($data['permite_lista_espera'] ?? 0),
            hasshirt: (int) ($data['hasshirt'] ?? 0),
            costoPolera: (float) ($data['costo_polera'] ?? 30),
             hasQuestion: (int) ($data['hasQuestion'] ?? 0),
            requiereTalla: (int) ($data['requiere_talla'] ?? 0),
            permiteInscripcionGrupal: (bool) ($data['permite_inscripcion_grupal'] ?? false),
            maxIntegrantesGrupo: (int) ($data['max_integrantes_grupo'] ?? 10),
            descuentoRegistrantePct: (float) ($data['descuento_registrante_pct'] ?? 0.10),
            hasTeam: (bool) ($data['hasTeam'] ?? $data['has_team'] ?? false),
            hasDelivery: (bool) ($data['hasDelivery'] ?? $data['has_delivery'] ?? false),
            hasDonation: (bool) ($data['hasDonation'] ?? $data['has_donation'] ?? false),
            hasPromoCode: (bool) ($data['hasPromoCode'] ?? $data['has_promo_code'] ?? false),
            requiereCategoria: (bool) ($data['requiere_categoria'] ?? true),
            souvenirs: array_map(
                fn(array $s) => SouvenirFormDTO::fromArray($s),
                $data['souvenirs'] ?? []
            ),
            preguntas: array_map(
                fn(array $p) => FormularioCamposDTO::fromArray($p),
                $data['preguntas'] ?? []
            ),
        );
    }
}
