<?php

namespace App\Actions;

use App\Models\PagoSaldoInscripcion;
use App\Models\Registration;
use App\Services\NotificacionService;
use App\Services\RegistrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pago online del saldo pendiente — autoservicio (22/10/2026). Se llama
 * cuando SIP/Multipago confirma el pago (vía payment_callback*.php) o
 * cuando el polling detecta el pago confirmado. A diferencia de
 * ConfirmarPagoAdicionalAction, no aplica ninguna edición — solo deja
 * registrations.saldo_pendiente en 0.
 */
class ConfirmarPagoSaldoAction
{
    public function __construct(
        private readonly NotificacionService $notificaciones,
        private readonly RegistrationService $registrationService,
    ) {
    }

    /**
     * @return array{pago: PagoSaldoInscripcion, registration: Registration|null}
     */
    public function handle(string $referencia): array
    {
        $pago = PagoSaldoInscripcion::where('referencia', $referencia)->firstOrFail();

        // Idempotente — SIP/Multipago pueden reintentar el callback.
        if ($pago->pago_status === 'paid') {
            return ['pago' => $pago, 'registration' => $this->registrationService->loadRelations($pago->registration)];
        }

        if ($pago->pago_status !== 'pending') {
            throw new \DomainException("Este pago ya no está pendiente (estado: {$pago->pago_status}).");
        }

        // Mutex contra Caja (22/10/2026) — dos canales pueden cobrar el
        // mismo saldo ahora (Caja presencial y este QR online). El UPDATE
        // condicional es el mutex real: si afecta 0 filas, el saldo ya lo
        // cobró el otro canal MIENTRAS este pago seguía pending. El dinero
        // ya lo cobró la pasarela en este punto — no se puede "no cobrar"
        // retroactivamente y no existe ningún mecanismo de reembolso
        // automático en el sistema hoy (mismo límite ya documentado en
        // ConfirmarPagoAdicionalAction) — se marca 'error' para revisión
        // manual (soporte) en vez de perder el pago silenciosamente.
        $aplicado = DB::transaction(function () use ($pago) {
            return Registration::where('id', $pago->registration_id)
                ->where('saldo_pendiente', '>', 0)
                ->update(['saldo_pendiente' => 0]);
        });

        if (!$aplicado) {
            $pago->update(['pago_status' => 'error']);
            Log::error("Pago de saldo {$pago->referencia} cobrado por la pasarela pero el saldo de la inscripción ya estaba en 0 (probablemente cobrado en Caja mientras tanto) — requiere revisión manual.");
            throw new \DomainException('El saldo de esta inscripción ya fue cobrado por otro medio.');
        }

        $pago->update(['pago_status' => 'paid', 'paid_at' => now()]);
        $pago->refresh();

        try {
            $this->notificaciones->notificarPagoSaldoConfirmado($pago);
        } catch (\Throwable $e) {
            Log::error("No se pudo notificar el pago de saldo confirmado {$pago->referencia}: {$e->getMessage()}");
        }
        $pago->refresh();

        return ['pago' => $pago, 'registration' => $this->registrationService->loadRelations($pago->registration)];
    }
}
