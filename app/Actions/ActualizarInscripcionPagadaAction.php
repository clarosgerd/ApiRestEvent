<?php

namespace App\Actions;

use App\DTOs\RegistrationDTO;
use App\Models\AuditLog;
use App\Models\Evento;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Services\RegistrationService;
use App\Support\CurrencyResolverData;
use App\Support\Taller\ValidarSeleccionesTallerAction;
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

            // Congresos con talleres (18/08/2026) — ver
            // brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
            // Mismo orden que ActualizarInscripcionAction: validación
            // ANTES del delete de participantes, para que la exclusión
            // del propio cupo funcione correctamente.
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

            ValidarSeleccionesTallerAction::runRequeridos($registrationDto);

            $this->registrationService->deactivateFormTypeIfCupoLleno($registration->form_types_id);

            // Inscripción en BOB y USD (18/08/2026) — ver
            // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Mismo
            // criterio que ActualizarInscripcionAction: validar y
            // normalizar el snapshot de moneda/tasa. En este path la
            // inscripción ya está pagada, así que la moneda probablemente
            // ya está persistida — si el cliente la cambia (algo que
            // solo debería permitir el admin) se revalida y se acepta
            // o se rechaza.
            $eventoRef = Evento::find($registration->evento_id);
            $monedaRef = strtoupper((string) ($data['moneda_pago'] ?? ($registration->moneda_pago ?? 'BOB')));
            $tasaRef   = isset($data['tipo_cambio_aplicado']) ? (float) $data['tipo_cambio_aplicado'] : null;
            $totalRef  = isset($data['total_pagado']) ? (float) $data['total_pagado'] : null;
            if ($monedaRef === 'USD' && (! $eventoRef || ! $eventoRef->acepta_usd)) {
                throw new \DomainException('Este evento solo acepta pago en BOB.');
            }
            // Precio USD fijo (19/08/2026) — ver
            // brain/PLAN-PRECIO-USD-FIJO-19082026.md.
            $resuelto  = ($monedaRef === 'USD' && $eventoRef && $eventoRef->usd_precio_fijo)
                ? CurrencyResolverData::resolverPrecioFijo($registrationDto, $eventoRef)
                : CurrencyResolverData::resolver(
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
