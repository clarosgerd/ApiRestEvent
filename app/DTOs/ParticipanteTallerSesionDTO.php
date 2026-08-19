<?php
namespace App\DTOs;

/**
 * Fila persistida del pivote `participante_taller_sesion` — expone el
 * snapshot financiero que se guarda al registrar / editar la inscripción.
 * Ver brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
 */
class ParticipanteTallerSesionDTO
{
    public function __construct(
        public int $participanteId,
        public int $sesionCongresoId,
        public int $tallerId,
        public float $unitPrice,
        public float $discount,
        public float $total,
    ){}

    public static function fromArray(array $data): self
    {
        return new self(
            participanteId: (int) $data['participante_id'],
            sesionCongresoId: (int) $data['sesion_congreso_id'],
            tallerId: (int) $data['taller_id'],
            unitPrice: (float) ($data['unit_price'] ?? 0),
            discount: (float) ($data['discount'] ?? 0),
            total: (float) ($data['total'] ?? 0),
        );
    }
}