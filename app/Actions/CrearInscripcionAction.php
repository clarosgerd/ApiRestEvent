<?php

namespace App\Actions;

use App\DTOs\ParticipantDTO;
use App\DTOs\RegistrationDTO;
use App\Models\Answer;
use App\Models\ContactoEmergenciaParticipante;
use App\Models\Equipo;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Models\Souvenir;
use App\Models\SouvenirParticipante;
use App\Services\NotificacionService;
use App\Services\RegistrationService;
use App\Support\DisponibilidadItemData;
use App\Support\CurrencyResolverData;
use App\Support\Categoria\ValidarCategoriaAction;
use App\Support\Taller\ResolverPrecioTallerData;
use App\Support\Taller\ValidarSeleccionesTallerAction;
use Illuminate\Support\Facades\DB;

class CrearInscripcionAction
{
    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly NotificacionService $notificaciones,
    ) {
    }

    /**
     * Crear una inscripción completa.
     */
    public function handle(RegistrationDTO $dto): Registration
    {
        try {
            $registration = $this->createInTransaction($dto);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Red de seguridad ante una colisión real bajo concurrencia: dos
            // requests simultáneas pueden pasar el chequeo
            // Registration::where('referencia', ...)->exists() de abajo antes
            // de que cualquiera haga commit — acá el UNIQUE de `referencia`
            // en la base es la última línea de defensa. Se normaliza al mismo
            // DomainException que ese chequeo (mismo mensaje, mismo tipo) para
            // que el controller la atrape igual y event/api/registro.php la
            // reconozca con su self-heal — pero solo si la violación es
            // realmente de esa columna: cualquier otro UNIQUE que choque en
            // esta transacción se relanza tal cual, no se enmascara.
            if (str_contains($e->getMessage(), 'referencia')) {
                throw new \DomainException("La referencia {$dto->reference} ya existe.");
            }
            throw $e;
        }

        // Fuera de la transacción a propósito: el envío de correo (I/O de red)
        // no debe mantener abierta la conexión/transacción de BD, y una falla
        // de SMTP no debe revertir el registro ya guardado.
        if ($registration->pago_status === 'pending') {
            $this->notificaciones->notificarInscripcionPendiente($registration);
        } elseif ($registration->pago_status === 'paid') {
            // Auto-confirmación en $0 (04/09/2026, ver más arriba) — nace
            // 'paid' directo, nunca pasa por
            // RegistrationService::updatePaymentStatus() (el único lugar
            // que normalmente dispara este correo) — sin este aviso, un
            // ponente que se registra gratis no recibiría ningún correo en
            // absoluto. notificarPagoConfirmado() ya es idempotente, así
            // que no hay riesgo de duplicar si alguna vez este flujo
            // también pasara por ese otro método.
            $this->notificaciones->notificarPagoConfirmado($registration);
        }

        return $registration;
    }

    private function createInTransaction(RegistrationDTO $dto): Registration
    {
        return DB::transaction(function () use ($dto) {

            if (
                Registration::where('referencia', $dto->reference)->exists()
            ) {
                // DomainException (no Exception a secas): así el controller la
                // atrapa y devuelve un 422 limpio en vez de un 500 sin manejar
                // — event/api/registro.php necesita ese 422 legible para
                // distinguir "mi propio reintento chocó con mi propia
                // referencia" de un error real.
                throw new \DomainException(
                    "La referencia {$dto->reference} ya existe."
                );
            }

            $this->validateFormTypeActivoYStock($dto);
            $this->validateFeePct($dto);
            // Inscripción en BOB y USD (18/08/2026) — ver
            // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Valida
            // que el snapshot de moneda/tasa enviado por el frontend sea
            // consistente con el grand_total BOB dentro de la tolerancia.
            // Si la moneda es BOB simplemente se ignora el snapshot y se
            // persiste null (default legacy).
            $this->validateMonedaPago($dto);

            // Auto-confirmar inscripciones en $0 (04/09/2026) — pedido:
            // formulario de ponente/staff sin costo. Antes de esto, TODA
            // inscripción nacía 'pending' sin importar el total — no tenía
            // sentido pedirle a alguien que "pague" un total de $0, así que
            // se confirma sola, sin pasar por ningún gateway. `paymentType`
            // se pisa a 'gratis' para que quede identificable en reportes
            // (tipo_pago es un string libre, no un enum de BD).
            if ($dto->totals->grandTotal <= 0.0) {
                $dto->paymentStatus = 'paid';
                $dto->paymentType = 'gratis';
            }

            foreach ($dto->participants as $participant) {
                $this->validateParticipantRegistration($dto, $participant);
                ValidarCategoriaAction::run($dto, $participant);
                $this->validateEquipo($dto, $participant);
                $this->validateDelivery($dto, $participant);
                // Congresos con talleres (18/08/2026) — pertenencia,
                // duplicado y solape, sin lock (la capacidad se valida
                // aparte, ya con lockForUpdate). Ver
                // brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
                ValidarSeleccionesTallerAction::runPorParticipante($dto, $participant);
            }

            // Capacidad de cada sesión con lock (transaccional) y
            // validación de talleres REQUIRED — ambas dentro del mismo
            // `DB::transaction` que ya existe.
            ValidarSeleccionesTallerAction::runCapacidad($dto);
            ValidarSeleccionesTallerAction::runRequeridos($dto);

            $this->validateDuplicateParticipants($dto);

            $registration = Registration::create([
                'referencia' => $dto->reference,
                'fecha' => $dto->date,
                'evento_id' => $dto->eventId,
                'evento_nombre' => $dto->eventName,
                'form_types_id' => $dto->formId,
                'tipo_pago' => $dto->paymentType,
                'pago_status' => $dto->paymentStatus,
                'pay_order_number' => $dto->payOrderNumber,
                // Inscripción en BOB y USD (18/08/2026) — snapshot
                // persistido por CurrencyResolverData (BOB => null/USD =>
                // valores validados). El default null de la BD hace que
                // eventos BOB-only existentes queden sin cambios.
                'moneda_pago' => $dto->monedaPago ?? 'BOB',
                'tipo_cambio_aplicado' => $dto->tipoCambioAplicado,
                'total_pagado' => $dto->totalPagado,
            ]);

            foreach ($dto->participants as $participant) {
                $this->createParticipant(
                    $registration,
                    $participant
                );
            }

            $this->registrationService->deactivateFormTypeIfCupoLleno($registration->form_types_id);

            $this->createTotals(
                $registration,
                $dto
            );

            $this->registrationService->syncPersonas($registration);

            return $this->registrationService->loadRelations(
                $registration
            );

        });
    }

    /**
     * Crear totales.
     */
    private function createTotals(
        Registration $registration,
        RegistrationDTO $dto
    ): void {

        RegistrationTotal::create([

            'registration_id' => $registration->id,
            'inscripcion' => $dto->totals->registration,
            'donacion' => $dto->totals->donation,
            'souvenirs' => $dto->totals->souvenirs,
            // Congresos con talleres (18/08/2026) — ver
            // brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
            // Default 0 si el evento no tiene talleres (compatibilidad).
            'talleres' => $dto->totals->talleres,
            'fee' => $dto->totals->fee,
            'descuento' => $dto->totals->discount,
            'descuento_registrante' => $dto->totals->groupDiscount,
            'grand_total' => $dto->totals->grandTotal,
        ]);
    }

    /**
     * Crear participante.
     */
    private function createParticipant(
        Registration $registration,
        ParticipantDTO $dto
    ): void {

        $this->registrationService->consumePromoCode($registration->evento_id, $dto->promoCode, $registration->id, $registration->form_types_id);

        $participant = Participante::create([

            'registration_id' => $registration->id,
            'nombre' => $dto->firstName,
            'apellido' => $dto->lastName,
            'alias' => $dto->alias,
            'genero' => $dto->gender,
            'tipo_documento' => $dto->documentType,
            'numero_documento' => $dto->documentNumber,
            'polera' => $dto->shirt,
            'precio_polera' => $dto->shirtPrice,
            'fecha_nacimiento' => sprintf(
                '%04d-%02d-%02d',
                $dto->birthDate->year,
                $dto->birthDate->month,
                $dto->birthDate->day
            ),

            'edad' => $dto->age,
            'correo' => $dto->email,
            'direccion' => $dto->address,
            'ciudad' => $dto->city,
            'telefono' => $dto->phone,
            'categoria' => $dto->category,
            'equipo_id' => $dto->equipoId,
            'quiere_delivery' => $dto->quiereDelivery,
            'estado_delivery' => $dto->quiereDelivery ? 'pendiente' : null,
            // Mapa de ubicación (12/08/2026) — solo tiene sentido si pidió
            // delivery; se ignora el pin si mandaron uno sin tildar el
            // checkbox (dato inconsistente del cliente, no se confía).
            'delivery_lat' => $dto->quiereDelivery ? $dto->deliveryLat : null,
            'delivery_lng' => $dto->quiereDelivery ? $dto->deliveryLng : null,
            'precio_categoria' => $dto->categoryPrice,
            'donacion' => $dto->donation,
            'promo_descuento' => $dto->promoDiscount,
            'promo_codigo' => $dto->promoCode,
            'subtotal' => $dto->subtotal,

        ]);

        ContactoEmergenciaParticipante::create([
            'participante_id' => $participant->id,
            'nombre' => $dto->emergencyContact->name,
            'celular' => $dto->emergencyContact->phone,
            'relacion' => $dto->emergencyContact->relationship,
        ]);

        foreach ($dto->souvenirs as $souvenir) {

            SouvenirParticipante::create([

                'participante_id' => $participant->id,
                'souvenir_id' => $souvenir->souvenir_id,
                'nombre' => $souvenir->name,
                'precio' => $souvenir->price,
                'talla' => $souvenir->talla,
                'sexo' => $souvenir->sexo,

            ]);

        }

        // Souvenirs invisibles para el participante (22/08/2026) — ver
        // RegistrationService::injectSouvenirsInvisibles(). $dto->souvenirs
        // acá viene de lo que mandó el cliente, que nunca puede incluir
        // uno invisible (nunca lo vio) — se asigna server-side.
        $this->registrationService->injectSouvenirsInvisibles(
            $participant,
            $registration->form_types_id,
            array_map(fn ($s) => (int) $s->souvenir_id, $dto->souvenirs)
        );

        foreach ($dto->answers as $answer) {
            Answer::create([
                'form_types_id'   => $answer->formTypeId,
                'question_id'     => $answer->questionId,
                'participante_id' => $participant->id,
                'value'           => $answer->value,
            ]);
        }

        // Congresos con talleres (18/08/2026) — ver
        // brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md. Por
        // cada selección de taller del participante: resolver precio
        // efectivo server-side (cliente nunca es autoridad — mismo
        // criterio que precioCategoria/fee) y persistir snapshot.
        if (! empty($dto->talleres)) {
            $evento = Evento::find($registration->evento_id);
            foreach ($dto->talleres as $tallerSel) {
                $sesion = \App\Models\SesionCongreso::with('taller')
                    ->where('id', $tallerSel->sesionCongresoId)
                    ->where('evento_id', $registration->evento_id)
                    ->first();

                if (! $sesion || ! $sesion->taller_id) {
                    continue; // ya validado arriba; defensa silenciosa
                }

                $unitPrice = ResolverPrecioTallerData::unitPrice(
                    $sesion->taller,
                    $sesion,
                    $evento
                );
                $total = ResolverPrecioTallerData::total(
                    $sesion->taller,
                    $sesion,
                    $evento
                );

                \App\Models\ParticipanteTallerSesion::create([
                    'participante_id'     => $participant->id,
                    'sesion_congreso_id'  => $sesion->id,
                    'taller_id'           => $sesion->taller_id,
                    'unit_price'          => $unitPrice,
                    'discount'            => 0,
                    'total'               => $total,
                ]);
            }
        }
    }

    /**
     * Kit/tallas/stock (11/08/2026) — ver
     * PRD-kit-tallas-stock-lista-espera.md. Dos chequeos, ambos dentro
     * de la transacción de `createInTransaction()` con `lockForUpdate()`
     * para que dos inscripciones simultáneas contra el último cupo/la
     * última talla no pasen ambas:
     *
     * 1. `form_types.activo` — hasta esta ronda, nada volvía a comprobar
     *    esta bandera antes de aceptar una inscripción nueva (se
     *    calculaba y se prendía, pero no bloqueaba nada — ver el
     *    "Hallazgo adicional" del PRD). Ahora si el form_type ya está
     *    desactivado (cupo lleno), se rechaza acá.
     * 2. Stock por talla/sexo de los ítems del kit elegidos (si el ítem
     *    no tiene ninguna fila en `item_stock`, tiene "disponibilidad no
     *    controlada" y no se bloquea — ver App\Support\DisponibilidadItemData).
     *    La demanda se agrega por combinación souvenir_id+talla+sexo
     *    across todos los participantes de esta misma inscripción, para
     *    no dejar pasar una sola inscripción con 5 participantes que
     *    piden la última talla si solo quedan 3.
     */
    private function validateFormTypeActivoYStock(RegistrationDTO $dto): void
    {
        $formType = FormType::where('id', $dto->formId)->lockForUpdate()->first();

        if (!$formType || !$formType->activo) {
            throw new \DomainException(
                'Este tipo de inscripción ya no tiene cupo disponible.'
            );
        }

        $selecciones = [];
        foreach ($dto->participants as $participant) {
            foreach ($participant->souvenirs as $souvenir) {
                $selecciones[] = [
                    'souvenir_id' => $souvenir->souvenir_id,
                    'talla' => $souvenir->talla,
                    'sexo' => $souvenir->sexo,
                ];
            }
        }

        // El cálculo/lock/throw en sí vive en DisponibilidadItemData
        // (13/08/2026) — reusado también al editar una inscripción
        // existente, ver RegistrationService::validateStockForParticipants().
        DisponibilidadItemData::validarDemandaOFail(
            DisponibilidadItemData::agregarDemanda($selecciones)
        );
    }

    /**
     * Cargo de servicio configurable por evento (11/08/2026) — ver
     * PRD-cargo-servicio-por-evento.md. Antes de este cambio,
     * `totals->fee` llegaba tal cual lo mandaba el cliente (calculado en
     * `elascenso/event`, con el 5% hardcodeado ahí) y ApiRestEvent lo
     * guardaba sin recalcularlo — mismo patrón de "confía ciegamente en
     * el proxy" que `precioCategoria` (ver el PRD de precios por
     * período). Se cierra acá: se recalcula con el `fee_pct` real del
     * evento y se rechaza si no coincide (tolerancia de 2 centavos por
     * redondeo, no por permisividad).
     *
     * Base del fee (19/08/2026) — inscripción + talleres por default.
     * Decisión revertida de la original "decisión T6" (18/08/2026, fee
     * solo sobre inscripción): SIP/Multipago cobran su comisión sobre el
     * monto total procesado, no por ítem, así que dejar los talleres
     * fuera del fee significa absorber ese costo de gateway en esa
     * porción — relevante porque un taller puede valer más que la
     * inscripción misma. Souvenirs y donación siguen excluidos (igual
     * que siempre).
     *
     * `fee_incluye_talleres` (mismo día) — configurable por evento, para
     * cuando el organizador no quiere aplicar el cargo a los talleres
     * (p.ej. convenio de gateway distinto). Default true.
     *
     * Cargo de servicio por souvenir individual (01/09/2026) — souvenirs
     * quedaban siempre afuera de la base; ahora cada souvenir puede
     * marcarse `aplica_cargo_servicio` (default false, opt-in) para sumar
     * su precio de catálogo a la base junto con inscripción/talleres. Se
     * recalcula acá con el precio REAL del souvenir (nunca el que mande
     * el cliente), mismo criterio "nunca confiar en el proxy" del resto
     * de este método.
     */
    private function validateFeePct(RegistrationDTO $dto): void
    {
        $evento = Evento::find($dto->eventId);
        if (!$evento) {
            return; // el chequeo de evento inexistente ya lo hizo elascenso/event antes de llegar acá
        }

        $souvenirIds = collect($dto->participants)
            ->flatMap(fn (ParticipantDTO $p) => collect($p->souvenirs)->pluck('souvenir_id'))
            ->unique()
            ->values();

        $souvenirsConCargo = Souvenir::whereIn('id', $souvenirIds)
            ->where('aplica_cargo_servicio', true)
            ->pluck('price', 'id');

        $totalSouvenirsConCargo = collect($dto->participants)
            ->flatMap(fn (ParticipantDTO $p) => $p->souvenirs)
            ->sum(fn ($sv) => (float) ($souvenirsConCargo[$sv->souvenir_id] ?? 0));

        $baseFee = $dto->totals->registration
            + ($evento->fee_incluye_talleres ? $dto->totals->talleres : 0)
            + $totalSouvenirsConCargo;
        $feeEsperado = round($baseFee * (float) $evento->fee_pct, 2);

        if (abs($feeEsperado - $dto->totals->fee) > 0.02) {
            throw new \DomainException(
                sprintf(
                    'El cargo de servicio no coincide con el vigente para este evento (%.2f%%). Recargá la página e intentá de nuevo.',
                    $evento->fee_pct * 100
                )
            );
        }
    }

    /**
     * Inscripción en BOB y USD (18/08/2026) — ver
     * brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Enforce:
     *
     * 1. Si el evento NO tiene `acepta_usd` y el cliente mandó USD →
     *    DomainException (defensa en profundidad: el proxy PHP
     *    `_registro_validacion.php` ya filtra antes, pero si alguien
     *    llama esta Action directo con un payload mal formado, no pasa).
     * 2. Si el cliente eligió USD con snapshot válido, CurrencyResolverData
     *    valida que `total_pagado ≈ grand_total_bob * tipo_cambio_aplicado`
     *    dentro de tolerancia.
     * 3. Normaliza el DTO: persiste los campos validados en el DTO para
     *    que el `Registration::create([...])` de más abajo los guarde.
     *
     * Llamar ANTES del loop de participantes (las validaciones de cada
     * participante no dependen de la moneda, pero el save sí — mejor
     * normalizar el DTO al inicio).
     */
    private function validateMonedaPago(RegistrationDTO $dto): void
    {
        $evento = Evento::find($dto->eventId);
        if (!$evento) {
            return; // chequeo previo del caller
        }

        $moneda = strtoupper((string) ($dto->monedaPago ?? 'BOB'));

        if ($moneda === 'USD' && ! $evento->acepta_usd) {
            throw new \DomainException(
                'Este evento solo acepta pago en BOB. Recargá la página e intentá de nuevo.'
            );
        }

        // Pago pendiente USD (24/08/2026) — defensa en profundidad, mismo
        // criterio que el chequeo equivalente en elascenso/event/api/registro.php:
        // "pendiente_usd" es exclusivamente USD; sin esto, un
        // moneda_pago=BOB desincronizado (ej. bug de estado en el
        // frontend) terminaba cobrando el precio_base normal en Bs para
        // un evento usdPrecioFijo en vez de rechazar el intento.
        if ($dto->paymentType === 'pendiente_usd' && $moneda !== 'USD') {
            throw new \DomainException(
                'Este método de pago solo acepta USD. Recargá la página e intentá de nuevo.'
            );
        }

        // Precio USD fijo (19/08/2026) — ver
        // brain/PLAN-PRECIO-USD-FIJO-19082026.md. Camino alternativo a
        // resolver() (tasa de cambio), sin tocarlo — solo aplica si el
        // evento lo tiene prendido.
        $resuelto = ($moneda === 'USD' && $evento->usd_precio_fijo)
            ? CurrencyResolverData::resolverPrecioFijo($dto, $evento)
            : CurrencyResolverData::resolver(
                (float) ($dto->totals->grandTotal ?? 0),
                $moneda,
                $dto->tipoCambioAplicado,
                $dto->totalPagado,
            );

        // Normalizamos el DTO para que `Registration::create()` y
        // `RegistrationResource` lean siempre los valores finales validados.
        // En BOB quedan null (default legacy, comportamiento idéntico a hoy).
        $dto->monedaPago = $moneda;
        $dto->totalPagado = $resuelto['total_pagado'];
        $dto->tipoCambioAplicado = $resuelto['tipo_cambio_aplicado'];
    }

    private function validateParticipantRegistration(
        RegistrationDTO $registrationDTO,
        ParticipantDTO $participantDTO
    ): void {

        $exists = Participante::query()
            ->where('tipo_documento', $participantDTO->documentType)
            ->where('numero_documento', $participantDTO->documentNumber)
            ->whereHas('registration', function ($query) use ($registrationDTO) {
                // Mismo criterio que RegistrationService::deactivateFormTypeIfCupoLleno():
                // una inscripción cancelled/failed no cuenta como "vigente".
                // Sin este filtro, a alguien cuyo cupo se revirtió por falta
                // de pago (notificaciones:revertir-cupo) le quedaba
                // bloqueado para siempre volver a inscribirse a este evento.
                $query->where('evento_id', $registrationDTO->eventId)
                    ->whereNotIn('pago_status', ['cancelled', 'failed']);
            })
            ->exists();

        if ($exists) {
            throw new \DomainException(
                sprintf(
                    'El participante %s %s con documento %s (%s) ya está registrado en el evento %d.',
                    $participantDTO->firstName,
                    $participantDTO->lastName,
                    $participantDTO->documentNumber,
                    $participantDTO->documentType,
                    $registrationDTO->eventId
                )
            );
        }
    }

    /**
     * Si el form_type tiene has_team=1 (inscripción individual con
     * pertenencia a un equipo, ver
     * brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §3), el equipo es
     * obligatorio y debe pertenecer al mismo evento.
     */
    private function validateEquipo(
        RegistrationDTO $registrationDTO,
        ParticipantDTO $participantDTO
    ): void {
        $formType = FormType::find($registrationDTO->formId);

        if (!$formType || !$formType->has_team) {
            return;
        }

        if (!$participantDTO->equipoId) {
            throw new \DomainException(
                'Este tipo de inscripción requiere seleccionar un equipo.'
            );
        }

        $equipoValido = Equipo::where('id', $participantDTO->equipoId)
            ->where('event_id', $registrationDTO->eventId)
            ->exists();

        if (!$equipoValido) {
            throw new \DomainException(
                "El equipo seleccionado no pertenece al evento {$registrationDTO->eventId}."
            );
        }
    }

    /**
     * quiereDelivery es opt-in (checkbox en el formulario, no automático) —
     * solo válido si el form_type ofrece esa opción. Ver
     * brain/PLAN-DELIVERY-31072026.md.
     */
    private function validateDelivery(
        RegistrationDTO $registrationDTO,
        ParticipantDTO $participantDTO
    ): void {
        if (!$participantDTO->quiereDelivery) {
            return;
        }

        $formType = FormType::find($registrationDTO->formId);

        if (!$formType || !$formType->has_delivery) {
            throw new \DomainException(
                'Este tipo de inscripción no ofrece delivery del kit.'
            );
        }
    }

    private function validateDuplicateParticipants(
        RegistrationDTO $dto
    ): void {

        $documents = [];

        foreach ($dto->participants as $participant) {
            $key = $participant->documentType . '-' . $participant->documentNumber;
            if (isset($documents[$key])) {
                throw new \DomainException(
                    "El participante con documento {$participant->documentNumber} está repetido en la solicitud."
                );
            }
            $documents[$key] = true;
        }
    }
}
