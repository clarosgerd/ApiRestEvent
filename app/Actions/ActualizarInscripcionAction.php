<?php

namespace App\Actions;

use App\DTOs\ParticipantDTO;
use App\DTOs\RegistrationDTO;
use App\Models\Evento;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Services\RegistrationService;
use App\Support\CurrencyResolverData;
use App\Support\Taller\ValidarSeleccionesTallerAction;
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

            // Congresos con talleres (18/08/2026) — ver
            // brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
            // Validar ANTES del `participants()->delete()`: las selecciones
            // actuales de esta inscripción se excluyen del conteo de
            // capacidad, así que tiene que correr antes de borrarlas. El
            // delete en cascada limpia `participante_taller_sesion`.
            $registrationDto = RegistrationDTO::fromArray(array_merge($data, [
                'evento_id'      => $registration->evento_id,
                'evento_nombre'  => $registration->evento_nombre,
                'pago_status'    => $registration->pago_status,
                'tipo_pago'      => $registration->tipo_pago,
                'pay_order_number' => $registration->pay_order_number,
                'referencia'     => $registration->referencia,
                'fecha'          => $registration->fecha?->toDateTimeString() ?? now()->toDateTimeString(),
                'form_types_id'  => $registration->form_types_id,
            ]));
            ValidarSeleccionesTallerAction::run($registrationDto);
            ValidarSeleccionesTallerAction::runCapacidad($registrationDto, $registration->id);

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

            // Requeridos (se ejecuta DESPUÉS del delete, pero ya validamos
            // pertenencia / duplicado / solape / capacidad arriba — acá
            // solo confirmamos que los REQUIRED quedaron cubiertos).
            ValidarSeleccionesTallerAction::runRequeridos($registrationDto);

            $this->registrationService->deactivateFormTypeIfCupoLleno($registration->form_types_id);

            // Inscripción en BOB y USD (18/08/2026) — ver
            // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Validar y
            // normalizar el snapshot de moneda/tasa contra el grand_total
            // BOB (mismo criterio que CrearInscripcionAction::validateMonedaPago).
            // Si el evento no acepta USD y el cliente mandó USD, rechaza.
            $eventoRef = Evento::find($registration->evento_id);
            $monedaRef = strtoupper((string) ($data['moneda_pago'] ?? ($registration->moneda_pago ?? 'BOB')));
            $tasaRef   = isset($data['tipo_cambio_aplicado']) ? (float) $data['tipo_cambio_aplicado'] : null;
            $totalRef  = isset($data['total_pagado']) ? (float) $data['total_pagado'] : null;
            if ($monedaRef === 'USD' && (! $eventoRef || ! $eventoRef->acepta_usd)) {
                throw new \DomainException('Este evento solo acepta pago en BOB.');
            }
            $resuelto  = CurrencyResolverData::resolver(
                (float) $data['totales']['grand_total'],
                $monedaRef,
                $tasaRef,
                $totalRef,
            );
            $registration->moneda_pago        = $monedaRef;
            $registration->tipo_cambio_aplicado = $resuelto['tipo_cambio_aplicado'];
            $registration->total_pagado       = $resuelto['total_pagado'];
            $registration->save();

            RegistrationTotal::create([
                'registration_id' => $registration->id,
                'inscripcion'     => $data['totales']['inscripcion'],
                'donacion'        => $data['totales']['donacion'],
                'souvenirs'       => $data['totales']['souvenirs'],
                // Congresos con talleres (18/08/2026).
                'talleres'        => $data['totales']['talleres'] ?? 0,
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
