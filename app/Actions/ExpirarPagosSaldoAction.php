<?php

namespace App\Actions;

use App\Models\PagoSaldoInscripcion;

/**
 * Pago online del saldo pendiente — autoservicio (22/10/2026). Copia
 * exacta del criterio de ExpirarPagosAdicionalesAction: ventana fija de
 * 60 minutos (un cobro puntual, no una inscripción nueva — no depende de
 * form_types.tiempo_expiracion_min). Solo toca
 * pagos_saldo_inscripcion.pago_status — nunca registrations, porque nada
 * se aplicó ahí hasta que el pago se confirma.
 */
class ExpirarPagosSaldoAction
{
    private const MINUTOS_EXPIRACION = 60;

    public function handle(): int
    {
        $expirados = 0;

        PagoSaldoInscripcion::where('pago_status', 'pending')
            ->where('created_at', '<=', now()->subMinutes(self::MINUTOS_EXPIRACION))
            ->chunkById(100, function ($pagos) use (&$expirados) {
                foreach ($pagos as $pago) {
                    $pago->update(['pago_status' => 'expired']);
                    $expirados++;
                }
            });

        return $expirados;
    }
}
