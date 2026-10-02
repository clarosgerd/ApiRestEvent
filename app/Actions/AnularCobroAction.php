<?php

namespace App\Actions;

use App\Models\AdminUser;
use App\Models\CajaMovimiento;
use App\Models\CajaTurno;
use App\Models\Registration;
use App\Services\RegistrationService;

/**
 * Caja: anular un cobro ya registrado (02/10/2026) — el cajero marca que un
 * cobro que ya entró (efectivo, QR, depósito, organizador) se devolvió
 * después por un motivo ajeno al sistema (chargeback bancario, devolución
 * manual fuera de la app). Se anula un movimiento PUNTUAL de la historia de
 * `caja_movimientos` de esa inscripción, no "el total neto pagado" — el
 * cajero elige cuál revertir.
 *
 * Crea un `CajaMovimiento` nuevo tipo='anulacion', monto NEGATIVO igual al
 * original y el MISMO `metodo_pago` del original (no uno elegido aparte):
 * el cierre de turno (`CajaTurnoController::cerrar()`) calcula
 * `monto_esperado` sumando solo `metodo_pago='EFECTIVO'` — si el cobro
 * original nunca tocó el cajón físico (QR/Depósito/Organizador), la
 * anulación tampoco debe afectarlo; heredar el método mantiene esa simetría
 * sin tener que duplicar esa regla acá.
 *
 * La anulación SIEMPRE cancela toda la inscripción (`pago_status='cancelled'`,
 * vía `RegistrationService::updatePaymentStatus()`, que ya se encarga de
 * avisar la reversión de cupo y de la purga de datos si corresponde) — no
 * existe hoy un estado intermedio "se devolvió una parte pero la inscripción
 * sigue en pie"; decisión explícita del usuario.
 */
class AnularCobroAction
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {
    }

    public function handle(
        Registration $registration,
        int $movimientoId,
        string $motivo,
        AdminUser $admin,
        CajaTurno $turno,
    ): CajaMovimiento {
        if ($registration->pago_status !== 'paid') {
            throw new \DomainException('Solo se puede anular un cobro de una inscripción pagada.');
        }

        $original = CajaMovimiento::where('registration_id', $registration->id)
            ->where('id', $movimientoId)
            ->first();

        if (! $original) {
            throw new \DomainException('Ese movimiento no corresponde a esta inscripción.');
        }

        if ($original->tipo === 'anulacion') {
            throw new \DomainException('No se puede anular una anulación.');
        }

        if ((float) $original->monto === 0.0) {
            throw new \DomainException('Ese movimiento no tiene monto real para anular.');
        }

        if ($original->anulacion()->exists()) {
            throw new \DomainException('Ese movimiento ya fue anulado antes.');
        }

        $anulacion = CajaMovimiento::create([
            'caja_turno_id'        => $turno->id,
            'evento_id'            => $registration->evento_id,
            'registration_id'      => $registration->id,
            'admin_user_id'        => $admin->id,
            'tipo'                 => 'anulacion',
            'monto'                => -(float) $original->monto,
            'metodo_pago'          => $original->metodo_pago,
            'motivo'               => $motivo,
            'anula_movimiento_id'  => $original->id,
        ]);

        $this->registrationService->updatePaymentStatus($registration->referencia, 'cancelled');

        return $anulacion;
    }
}
