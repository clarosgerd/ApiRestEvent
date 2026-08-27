<?php

namespace App\Actions;

use App\Models\PagoAdicionalInscripcion;
use App\Models\Registration;
use Illuminate\Support\Str;

/**
 * Cobro real por SIP del monto adicional (26/08/2026) — ver
 * PLAN-COBRO-SIP-ADICIONAL-26082026.md. Crea el registro `pending` del
 * intento de pago; NO toca la inscripción ni el cupo del taller para
 * nada todavía — eso solo ocurre si/cuando ConfirmarPagoAdicionalAction
 * confirma el pago.
 */
class GenerarPagoAdicionalAction
{
    public function handle(Registration $registration, array $participantes, array $totales, float $monto, string $monedaPago = 'BOB'): PagoAdicionalInscripcion
    {
        if ($registration->pago_status !== 'paid') {
            throw new \DomainException('Esta operación solo aplica a inscripciones pagadas.');
        }

        // Prefijo 'AD-' (26/08/2026) — deliberadamente distinto del 'LA-'
        // que usa elascenso/event para referencias de inscripción, así el
        // alias de este pago nunca puede colisionar con
        // `registrations.referencia` (payment_callback.php resuelve por
        // ese orden: inscripción real primero, este pago recién si no
        // matchea nada).
        $referencia = 'AD-' . strtoupper(Str::random(8));

        return PagoAdicionalInscripcion::create([
            'registration_id' => $registration->id,
            'referencia' => $referencia,
            'monto' => $monto,
            'moneda_pago' => $monedaPago,
            'participantes_payload' => $participantes,
            'totales_payload' => $totales,
            'pago_status' => 'pending',
        ]);
    }
}
