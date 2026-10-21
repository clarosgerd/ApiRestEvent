<?php

namespace App\Actions;

use App\Models\PagoSaldoInscripcion;
use App\Models\Registration;
use Illuminate\Support\Str;

/**
 * Pago online del saldo pendiente — autoservicio (22/10/2026, pedido real
 * del usuario). Crea el registro `pending` del intento de pago; NO toca
 * `registrations.saldo_pendiente` para nada todavía — eso solo ocurre si/
 * cuando ConfirmarPagoSaldoAction confirma el pago.
 *
 * Fee solo en este canal (22/10/2026, decisión revisada con el usuario):
 * cubre el costo real de la pasarela de ESTA transacción online, nunca se
 * cobra cuando el saldo se cobra en Caja (efectivo, ver
 * CajaController::cobrarSaldo(), que sigue cobrando exactamente
 * saldo_pendiente sin fee).
 */
class GenerarPagoSaldoAction
{
    public function handle(Registration $registration): PagoSaldoInscripcion
    {
        if ($registration->pago_status !== 'paid') {
            throw new \DomainException('Esta operación solo aplica a inscripciones pagadas.');
        }

        $saldo = (float) $registration->saldo_pendiente;
        if ($saldo <= 0) {
            throw new \DomainException('Esta inscripción no tiene saldo pendiente.');
        }

        $feePct = (float) ($registration->evento?->fee_pct ?? 0.05);
        $fee = round($saldo * $feePct, 2);
        $monto = round($saldo + $fee, 2);

        // Prefijo 'SA-' — distinto de 'AD-' (pago adicional) y de 'LA-'
        // (referencia de inscripción real), nunca colisiona con ninguno.
        $referencia = 'SA-' . strtoupper(Str::random(8));

        return PagoSaldoInscripcion::create([
            'registration_id' => $registration->id,
            'referencia' => $referencia,
            'saldo' => $saldo,
            'fee' => $fee,
            'monto' => $monto,
            'moneda_pago' => 'BOB',
            'pago_status' => 'pending',
        ]);
    }
}
