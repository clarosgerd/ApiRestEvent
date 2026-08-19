<?php
namespace App\DTOs;

/**
 * Sesión de un taller tal como viaja dentro del payload de inscripción
 * (solo lo que el cliente puede mandar: identificadores + precio que el
 * backend recalcula server-side). Ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
 */
class TallerSesionDTO
{
    public function __construct(
        public int $tallerId,
        public int $sesionCongresoId,
        public ?float $unitPrice = null,
        public ?float $discount = null,
        public ?float $total = null,
    ){}

    public static function fromArray(array $data): self
    {
        return new self(
            tallerId: (int) $data['taller_id'],
            sesionCongresoId: (int) $data['sesion_congreso_id'],
            unitPrice: isset($data['unit_price']) ? (float) $data['unit_price'] : null,
            discount: isset($data['discount']) ? (float) $data['discount'] : null,
            total: isset($data['total']) ? (float) $data['total'] : null,
        );
    }
}