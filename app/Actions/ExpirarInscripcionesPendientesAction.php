<?php

namespace App\Actions;

use App\Models\Registration;
use App\Services\RegistrationService;

/**
 * Cancela inscripciones `pending` cuyo `form_types.tiempo_expiracion_min`
 * ya venció desde que se crearon — hasta el 09/08/2026 esa columna se
 * guardaba pero nunca se usaba para nada (grepeado antes de escribir esto:
 * 0 lugares la leían fuera del alta/edición del form_type).
 *
 * Complementa, no reemplaza, a `notificaciones:revertir-cupo`: ese otro
 * ciclo cuenta hacia atrás desde la fecha del evento (recordatorio 30/15
 * días + gracia) y tiene sentido para inscripciones de eventos lejanos.
 * Este cubre el caso corto — alguien reserva cupo con un pago QR/pasarela
 * que nunca completa, y el evento puede estar meses en el futuro: sin
 * esto, ese cupo quedaba "secuestrado" hasta que el ciclo de 15 días
 * entrara en juego, mucho más tarde. Pensado para correr cada pocos
 * minutos (ver routes/console.php), no diario.
 *
 * Reusa RegistrationService::updatePaymentStatus() — mismo camino que
 * usa notificaciones:revertir-cupo, así que también dispara
 * notificarReversionCupo() (mismo correo/WhatsApp, "cancelado por falta
 * de pago", sin mención a 15 días — el mensaje ya es genérico) y libera
 * el cupo (deactivateFormTypeIfCupoLleno() solo desactiva, nunca
 * reactiva — reabrir el form_type sigue siendo decisión manual del
 * organizador, sin cambios en ese criterio).
 */
class ExpirarInscripcionesPendientesAction
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {
    }

    public function handle(): int
    {
        $expiradas = 0;

        Registration::where('pago_status', 'pending')
            ->with('formType')
            ->chunkById(100, function ($registrations) use (&$expiradas) {
                foreach ($registrations as $registration) {
                    $minutos = $registration->formType->tiempo_expiracion_min ?? 0;

                    // 0 (o form_type borrado/sin relación) = sin expiración
                    // configurada — no tocar. NOT NULL en la BD, pero
                    // defensivo por si algún valor legacy quedó en 0.
                    if ($minutos <= 0) {
                        continue;
                    }

                    $vence = $registration->created_at->copy()->addMinutes($minutos);

                    if (now()->greaterThanOrEqualTo($vence)) {
                        $this->registrationService->updatePaymentStatus($registration->referencia, 'cancelled');
                        $expiradas++;
                    }
                }
            });

        return $expiradas;
    }
}
