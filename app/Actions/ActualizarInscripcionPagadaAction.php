<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Services\RegistrationService;
use Illuminate\Support\Facades\DB;

class ActualizarInscripcionPagadaAction
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {
    }

    /**
     * Actualizar inscripción pagada con costo adicional.
     *
     * @return array{registration: Registration, costo_adicion: float}
     */
    public function handle(string $reference, array $data): array
    {
        return DB::transaction(function () use ($reference, $data) {

            $registration = Registration::with('formType')
                ->where('referencia', $reference)
                ->firstOrFail();

            if ($registration->pago_status !== 'paid') {
                throw new \DomainException(
                    'Esta operación solo aplica a inscripciones pagadas.'
                );
            }

            $costoEdicion = $registration->formType->costo_edicion ?? 0;

            $this->registrationService->validateDuplicateParticipantsFromData($data);

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

            AuditLog::create([
                'registration_id' => $registration->id,
                'usuario'         => $data['_usuario'] ?? null,
                'costo_adicion'   => $costoEdicion,
            ]);

            return [
                'registration'  => $this->registrationService->loadRelations($registration),
                'costo_adicion' => $costoEdicion,
            ];
        });
    }
}
