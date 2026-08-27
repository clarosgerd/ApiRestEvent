<?php

namespace App\Actions;

use App\Models\PagoAdicionalInscripcion;

/**
 * Cobro real por SIP del monto adicional (26/08/2026) — ver
 * PLAN-COBRO-SIP-ADICIONAL-26082026.md. Mismo patrón que
 * ExpirarInscripcionesPendientesAction, pero mucho más simple: como
 * ConfirmarPagoAdicionalAction nunca aplica nada hasta que el pago está
 * `paid`, expirar acá es un no-op sobre la inscripción real — no hay
 * cupo que liberar ni nada que revertir, solo se marca el intento como
 * `expired` para que no quede "pending" para siempre.
 *
 * Ventana fija (no depende de `form_types.tiempo_expiracion_min`, ver
 * $minutosExpiracion) — a diferencia de una inscripción completa, esto es
 * un cobro puntual sobre algo que ya está pagado, no tiene sentido atarlo
 * a la configuración de expiración de inscripciones nuevas del form_type.
 */
class ExpirarPagosAdicionalesAction
{
    private const MINUTOS_EXPIRACION = 60;

    public function handle(): int
    {
        $expirados = 0;

        PagoAdicionalInscripcion::where('pago_status', 'pending')
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
