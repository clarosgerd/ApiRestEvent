<?php

namespace App\Actions;

use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Services\RegistrationService;
use Illuminate\Support\Facades\DB;

class ActualizarInscripcionAction
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {
    }

    /**
     * Actualizar inscripción completa (solo si no está pagada).
     */
    public function handle(string $reference, array $data): Registration
    {
        return DB::transaction(function () use ($reference, $data) {

            $registration = Registration::where('referencia', $reference)->firstOrFail();

            if (in_array($registration->pago_status, ['paid', 'cancelled'], true)) {
                throw new \DomainException(
                    $registration->pago_status === 'paid'
                        ? 'No se puede modificar una inscripción ya pagada.'
                        : 'No se puede modificar una inscripción cancelada.'
                );
            }

            $this->registrationService->validateDuplicateParticipantsFromData($data);

            // Libera los códigos de promoción que esta misma inscripción tuviera
            // consumidos antes de recrear los participantes — si el organizador
            // mantiene el mismo código, createParticipantFromData() lo vuelve a
            // marcar usado sin rechazarse a sí mismo; si lo cambia o lo quita,
            // el código viejo queda libre para otra inscripción.
            $this->registrationService->releasePromoCodes($registration->id);

            $registration->participants()->delete();
            $registration->totals()->delete();

            // Revalidación de stock (13/08/2026) — ver
            // PLAN-STOCK-SOUVENIRS-SIMPLES-13082026.md punto 2. Después del
            // delete de arriba (el propio consumo anterior de esta
            // inscripción ya no cuenta) y antes de recrear los
            // participantes.
            $this->registrationService->validateStockForParticipants($data['participantes']);

            foreach ($data['participantes'] as $participantData) {
                $this->registrationService->createParticipantFromData($registration, $participantData);
            }

            $this->registrationService->deactivateFormTypeIfCupoLleno($registration->form_types_id);

            RegistrationTotal::create([
                'registration_id' => $registration->id,
                'inscripcion'     => $data['totales']['inscripcion'],
                'donacion'        => $data['totales']['donacion'],
                'souvenirs'       => $data['totales']['souvenirs'],
                'fee'             => $data['totales']['fee'],
                'descuento'       => $data['totales']['descuento'],
                'descuento_registrante' => $data['totales']['descuento_registrante'] ?? 0,
                'grand_total'     => $data['totales']['grand_total'],
            ]);

            $this->registrationService->syncPersonas($registration);

            return $this->registrationService->loadRelations($registration);
        });
    }
}
