<?php
namespace App\DTOs;

/**
 * Totales de una inscripción. La fórmula del grand_total desde el
 * 18/08/2026 es:
 *   inscripcion + donacion + souvenirs + talleres + fee
 *     - descuento - descuento_registrante
 * Ver brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md. La
 * columna `talleres` es nueva; default 0 mantiene compatibilidad con
 * eventos sin talleres (legacy).
 */
class TotalsDTO
{
    public function __construct(
        public float $registration,
        public float $donation,
        public float $souvenirs,
        public float $talleres,
        public float $fee,
        public float $discount,
        public float $groupDiscount,
        public float $grandTotal
    ){}
    public static function fromArray(array $data): self
    {
        return new self(
            registration: (float) $data['inscripcion'],
            donation: (float) $data['donacion'],
            souvenirs: (float) $data['souvenirs'],
            talleres: (float) ($data['talleres'] ?? 0),
            fee: (float) $data['fee'],
            discount: (float) $data['descuento'],
            groupDiscount: (float) ($data['descuento_registrante'] ?? 0),
            grandTotal: (float) $data['grand_total']
        );
    }
}