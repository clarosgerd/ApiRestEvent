<?php

namespace App\Actions;

use App\Models\AdminUser;
use App\Models\Participante;
use App\Models\Persona;
use App\Services\AdminAuditLogger;
use Carbon\CarbonInterface;

/**
 * Marcar a un participante como presente (acreditación / check-in,
 * 02/10/2026) — extraída de `ParticipanteController::checkin()` para
 * reusarla sin HTTP desde `StaffAppController::checkinBulk()` (app de staff
 * offline), sin duplicar la lógica. `ParticipanteController::checkin()`
 * pasa a ser un wrapper fino sobre esta Action.
 *
 * Gate real de pago, no solo visual. Idempotente: reescanear a alguien ya
 * acreditado NO es un error — devuelve el timestamp original sin pisarlo.
 * Acepta el actor real (AdminUser en el panel, Persona en la app de staff)
 * para que la auditoría (`admin_audit_logs`) quede trazable en los 2 casos.
 */
class CheckinParticipanteAction
{
    public const REJECTED_UNPAID = 'rejected_unpaid';

    public const ALREADY = 'already';

    public const CHECKED_IN = 'checked_in';

    /**
     * @return array{status: string, participante: Participante}
     */
    public function handle(Participante $participante, AdminUser|Persona $actor, ?CarbonInterface $checkedInAt = null): array
    {
        $participante->loadMissing('registration');

        if ($participante->registration->pago_status !== 'paid') {
            return ['status' => self::REJECTED_UNPAID, 'participante' => $participante];
        }

        if ($participante->checked_in_at) {
            return ['status' => self::ALREADY, 'participante' => $participante];
        }

        $before = $participante->toArray();
        $participante->update(['checked_in_at' => $checkedInAt ?? now()]);

        AdminAuditLogger::log(
            'checkin',
            'participante',
            $participante->id,
            (int) $participante->registration->evento_id,
            $before,
            $participante->toArray(),
            actor: $actor,
        );

        return ['status' => self::CHECKED_IN, 'participante' => $participante];
    }
}
