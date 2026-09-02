<?php

namespace App\Actions;

use App\Models\PagoAdicionalInscripcion;
use App\Services\NotificacionService;
use App\Services\RegistrationService;
use Illuminate\Support\Facades\Log;

/**
 * Cobro real por SIP del monto adicional (26/08/2026) — ver
 * PLAN-COBRO-SIP-ADICIONAL-26082026.md. Se llama cuando SIP confirma el
 * pago (vía payment_callback.php) o cuando el polling detecta el pago
 * confirmado. Recién ACÁ se aplica el cambio real (taller nuevo/etc.),
 * reusando ActualizarInscripcionPagadaAction tal cual — nada de lógica de
 * negocio duplicada.
 */
class ConfirmarPagoAdicionalAction
{
    public function __construct(
        private readonly ActualizarInscripcionPagadaAction $actualizarInscripcionPagada,
        private readonly RegistrationService $registrationService,
        private readonly NotificacionService $notificaciones,
    ) {
    }

    /**
     * @return array{pago: PagoAdicionalInscripcion, registration: \App\Models\Registration|null}
     */
    public function handle(string $referencia): array
    {
        $pago = PagoAdicionalInscripcion::where('referencia', $referencia)->firstOrFail();

        // Idempotente — SIP puede reintentar el callback; si ya está
        // 'paid', no se vuelve a aplicar el cambio (evitaría duplicar el
        // taller / recobrar). loadRelations() acá es necesario: sin esto
        // $pago->registration llega sin participants/talleres eager-cargados
        // y RegistrationCollectionResource (whenLoaded()) los omitiría
        // silenciosamente en la respuesta — mismo bug que ya se encontró y
        // arregló en show() (PagoAdicionalController).
        if ($pago->pago_status === 'paid') {
            return ['pago' => $pago, 'registration' => $this->registrationService->loadRelations($pago->registration)];
        }

        if ($pago->pago_status !== 'pending') {
            throw new \DomainException("Este pago adicional ya no está pendiente (estado: {$pago->pago_status}).");
        }

        // Nota deliberada: NO se envuelve esta llamada en un DB::transaction
        // propio — ActualizarInscripcionPagadaAction ya maneja la suya
        // internamente. Envolverla acá también rompería el catch de abajo:
        // un `throw` dentro de una transacción propia haría rollback
        // también del `$pago->update(['pago_status' => 'error'])`,
        // dejando la fila incorrectamente en 'pending' para siempre (bug
        // real encontrado escribiendo el test de este caso).
        try {
            // requierePagoEnSitio se deja en su default (false): si
            // llegamos hasta acá es porque SIP ya confirmó el cobro
            // online — el taller nuevo no necesita cobrarse en el evento
            // (ver reporte de talleres confiable, 27/08/2026).
            $result = $this->actualizarInscripcionPagada->handle(
                $pago->registration->referencia,
                [
                    'participantes' => $pago->participantes_payload,
                    'totales' => $pago->totales_payload,
                    '_usuario' => 'sip:' . $pago->referencia,
                ],
                permiteCambioCategoria: false,
            );
        } catch (\Throwable $e) {
            // El dinero ya lo cobró SIP en este punto — no se puede "no
            // cobrar" retroactivamente. Se marca 'error' para revisión
            // manual (soporte) en vez de perder el pago silenciosamente;
            // no existe ningún mecanismo de reembolso automático en el
            // sistema hoy (fuera de alcance, ver plan).
            $pago->update(['pago_status' => 'error']);
            throw $e;
        }

        $pago->update(['pago_status' => 'paid', 'paid_at' => now()]);
        $pago->refresh();

        // Correo de confirmación por pago adicional (02/09/2026) — hueco
        // real encontrado en un incidente de UAT: hasta hoy, ni siquiera un
        // pago adicional aplicado con éxito avisaba nada al participante.
        // No debe tumbar la confirmación si falla (NotificacionService ya
        // atrapa errores de SMTP por su cuenta; este try/catch es defensa
        // extra por si algo más falla armando el correo).
        try {
            $this->notificaciones->notificarPagoAdicionalConfirmado($pago);
        } catch (\Throwable $e) {
            Log::error("No se pudo notificar el pago adicional confirmado {$pago->referencia}: {$e->getMessage()}");
        }

        return ['pago' => $pago, 'registration' => $result['registration']];
    }
}
