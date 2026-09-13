<?php

namespace App\DTOs;

class PromoCodeDTO
{
    public function __construct(
        public string $promoCode,
        public ?float $price,
        public string $discountType,
        public ?float $discountPercent,
        // Multi-uso (13/09/2026) — el wizard de alta de evento
        // (admin-eventos/create.blade.php) crea códigos anidados vía este
        // DTO, camino distinto de PromoCodeController::store() (que ya
        // default-ea a 1). Sin esto, CrearEventoAction::createPromoCodes()
        // ignoraría en silencio el "Usos máximos" que el admin cargó ahí.
        public int $maxUses = 1,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            promoCode: $data['promo_code'],
            price: isset($data['price']) ? (float) $data['price'] : null,
            discountType: $data['discount_type'] ?? 'fixed_price',
            discountPercent: isset($data['discount_percent']) ? (float) $data['discount_percent'] : null,
            maxUses: isset($data['max_uses']) && (int) $data['max_uses'] > 0 ? (int) $data['max_uses'] : 1,
        );
    }
}
