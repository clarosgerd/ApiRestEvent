<?php

namespace App\Actions;

use App\DTOs\ParticipantDTO;
use App\DTOs\RegistrationDTO;
use App\Models\Answer;
use App\Models\Category;
use App\Models\ContactoEmergenciaParticipante;
use App\Models\Equipo;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Models\SouvenirParticipante;
use App\Services\NotificacionService;
use App\Services\RegistrationService;
use App\Support\DisponibilidadItemData;
use App\Support\PrecioVigenteData;
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

            foreach ($dto->participants as $participant) {
                $this->validateParticipantRegistration($dto, $participant);
                $this->validatePrecioCategoria($dto, $participant);
                $this->validateEquipo($dto, $participant);
                $this->validateDelivery($dto, $participant);
            }

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

        foreach ($dto->answers as $answer) {
            Answer::create([
                'form_types_id'   => $answer->formTypeId,
                'question_id'     => $answer->questionId,
                'participante_id' => $participant->id,
                'value'           => $answer->value,
            ]);
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
     */
    private function validateFeePct(RegistrationDTO $dto): void
    {
        $evento = Evento::find($dto->eventId);
        if (!$evento) {
            return; // el chequeo de evento inexistente ya lo hizo elascenso/event antes de llegar acá
        }

        $feeEsperado = round($dto->totals->registration * (float) $evento->fee_pct, 2);

        if (abs($feeEsperado - $dto->totals->fee) > 0.02) {
            throw new \DomainException(
                sprintf(
                    'El cargo de servicio no coincide con el vigente para este evento (%.2f%%). Recargá la página e intentá de nuevo.',
                    $evento->fee_pct * 100
                )
            );
        }
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
     * Precios por período (12/08/2026) — ver PRD-precios-periodos-fechas.md,
     * Hallazgo #2 y sección 0. Antes ApiRestEvent guardaba
     * `precio_categoria` tal cual llegaba en el request, confiando
     * ciegamente en el proxy (`elascenso/event`) — cerrado acá, mismo
     * criterio que `validateFeePct()`. Ramifica por
     * `formType.requiere_categoria`:
     * - true: el precio esperado es el vigente de la categoría (períodos
     *   o `categories.price`, ver PrecioVigenteData::paraCategoria()).
     * - false: no hay categoría real que resolver — el precio esperado
     *   es `form_types.precio_base` directo.
     * Aplica siempre, con o sin períodos configurados — defensa en
     * profundidad real, no placebo.
     */
    private function validatePrecioCategoria(
        RegistrationDTO $registrationDTO,
        ParticipantDTO $participantDTO
    ): void {
        $formType = FormType::find($registrationDTO->formId);
        if (!$formType) {
            return; // ya se validó que el form_type existe antes de llegar acá
        }

        if ($formType->requiere_categoria) {
            $category = Category::where('id', $participantDTO->category)
                ->where('event_id', $registrationDTO->eventId)
                ->first();

            if (!$category) {
                throw new \DomainException(
                    "La categoría '{$participantDTO->category}' no es válida para este evento."
                );
            }

            $precioVigente = PrecioVigenteData::paraCategoria($category)['precio'];

            if (abs($precioVigente - $participantDTO->categoryPrice) > 0.01) {
                throw new \DomainException(
                    'El precio de la categoría no coincide con el vigente. Recargá la página e intentá de nuevo.'
                );
            }

            return;
        }

        if (abs((float) $formType->precio_base - $participantDTO->categoryPrice) > 0.01) {
            throw new \DomainException(
                'El precio de inscripción no coincide con el vigente. Recargá la página e intentá de nuevo.'
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
