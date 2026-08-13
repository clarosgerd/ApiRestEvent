<?php

namespace App\Services;

use App\Models\Participante;
use App\Models\FormType;
use App\Models\Persona;
use App\Models\PromoCode;
use App\Models\SouvenirParticipante;
use App\Models\Registration;
use App\Models\ContactoEmergenciaParticipante;
use App\Models\Answer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class RegistrationService
{
    public function __construct(
        private readonly NotificacionService $notificaciones
    ) {
    }

    /**
     * Cuenta los participantes de inscripciones vigentes (ni canceladas ni
     * fallidas) de este form_type y, si ya alcanzaron el cupo_total, lo
     * desactiva (activo = 0) para que deje de ofrecerse en el frontend.
     * Es una desactivación, nunca una reactivación: si alguien cancela y
     * libera cupo, reactivar `activo` es una decisión manual del organizador.
     *
     * Público (no privado) porque además del chequeo "en caliente" de
     * create()/update()/updatePaidRegistration() acá abajo, también lo
     * reusa App\Actions\SweepFormTypesCupoLlenoAction (la red de seguridad
     * diaria) — colaborador compartido, no se duplica la regla.
     */
    public function deactivateFormTypeIfCupoLleno(int $formTypeId): void
    {
        $formType = FormType::find($formTypeId);

        if (!$formType || !$formType->activo) {
            return;
        }

        if ($formType->inscritosVigentes() >= $formType->cupo_total) {
            $formType->update(['activo' => false]);
        }
    }

    /**
     * Buscar por referencia.
     */
    public function findByReference(
        string $reference
    ): Registration {

        $registration = Registration::where(
            'referencia',
            $reference
        )->first();

        if (!$registration) {
            throw new ModelNotFoundException(
                "No existe la referencia {$reference}"
            );
        }

        return $this->loadRelations(
            $registration
        );
    }

    /**
     * Actualizar estado del pago.
     */
    public function updatePaymentStatus(
        string $reference,
        string $status
    ): Registration {

        $registration = Registration::where(
            'referencia',
            $reference
        )->firstOrFail();

        $registration->update([
            'pago_status' => $status
        ]);

        if ($status === 'paid') {
            $this->notificaciones->notificarPagoConfirmado($registration);
        } elseif ($status === 'cancelled') {
            // Cubre tanto la reversión automática de cupo (comando
            // notificaciones:revertir-cupo) como una cancelación manual vía
            // este mismo endpoint — cualquier transición a cancelled avisa.
            $this->notificaciones->notificarReversionCupo($registration);
        }

        return $this->loadRelations(
            $registration
        );
    }

    /**
     * Eliminar inscripción.
     */
    public function delete(
        string $reference
    ): void {

        $registration = Registration::where(
            'referencia',
            $reference
        )->firstOrFail();

        $registration->delete();
    }

    /**
     * Crear participante desde array raw (para actualización) — colaborador
     * compartido por App\Actions\ActualizarInscripcionAction y
     * App\Actions\ActualizarInscripcionPagadaAction.
     */
    public function createParticipantFromData(Registration $registration, array $data): void
    {
        $this->consumePromoCode($registration->evento_id, $data['promoCodigo'] ?? '', $registration->id, $registration->form_types_id);

        $birth = $data['nacimiento'];

        $participant = Participante::create([
            'registration_id'  => $registration->id,
            'nombre'           => $data['nombre'],
            'apellido'         => $data['apellido'],
            'alias'            => $data['alias'] ?? '',
            'genero'           => $data['genero'] ?? 'Masculino',
            'tipo_documento'   => $data['tipoDocumento'] ?? 'DNI',
            'numero_documento' => $data['numeroDocumento'],
            'polera'           => $data['polera'] ?? '',
            'precio_polera'    => $data['precioPolera'] ?? 0,
            'fecha_nacimiento' => sprintf('%04d-%02d-%02d', $birth['anio'], $birth['mes'], $birth['dia']),
            'edad'             => $data['edad'],
            'correo'           => $data['correo'],
            'direccion'        => $data['direccion'] ?? '',
            'ciudad'           => $data['ciudad'] ?? '',
            'telefono'         => $data['telefono'] ?? '',
            'categoria'        => $data['categoria'],
            'equipo_id'        => $data['equipoId'] ?? null,
            'quiere_delivery'  => $data['quiereDelivery'] ?? false,
            'estado_delivery'  => ($data['quiereDelivery'] ?? false) ? 'pendiente' : null,
            // Mapa de ubicación (12/08/2026) — mismo criterio que
            // CrearInscripcionAction: solo se guarda el pin si pidió
            // delivery.
            'delivery_lat'     => ($data['quiereDelivery'] ?? false) ? ($data['deliveryLat'] ?? null) : null,
            'delivery_lng'     => ($data['quiereDelivery'] ?? false) ? ($data['deliveryLng'] ?? null) : null,
            'precio_categoria' => $data['precioCategoria'],
            'donacion'         => $data['donacion'] ?? 0,
            'promo_descuento'  => $data['promoDescuento'] ?? 0,
            'promo_codigo'     => $data['promoCodigo'] ?? '',
            'subtotal'         => $data['subtotal'],
        ]);

        $emergency = $data['contacto_emergencia'];
        ContactoEmergenciaParticipante::create([
            'participante_id' => $participant->id,
            'nombre'          => $emergency['nombre'],
            'celular'         => $emergency['celular'],
            'relacion'        => $emergency['relacion'],
        ]);

        foreach ($data['souvenirs'] ?? [] as $souvenir) {
            SouvenirParticipante::create([
                'participante_id' => $participant->id,
                'souvenir_id'     => $souvenir['id'],
                'nombre'          => $souvenir['nombre'],
                'precio'          => $souvenir['precio'],
                // Kit/tallas/stock (11/08/2026) — ver
                // PRD-kit-tallas-stock-lista-espera.md. No se revalida
                // stock acá (a diferencia de CrearInscripcionAction) —
                // editar una inscripción ya paga/pendiente para cambiar
                // de talla sin revalidar cupo es un gap conocido, ver
                // deploy checklist.
                'talla'           => $souvenir['talla'] ?? null,
                'sexo'            => $souvenir['sexo'] ?? null,
            ]);
        }

        foreach ($data['answers'] ?? [] as $answer) {
            Answer::create([
                'form_types_id'   => $answer['form_types_id'],
                'question_id'     => $answer['question_id'],
                'participante_id' => $participant->id,
                'value'           => $answer['value'],
            ]);
        }
    }

    /**
     * Validar que no hayan documentos duplicados en el request de
     * actualización — colaborador compartido por
     * App\Actions\ActualizarInscripcionAction y
     * App\Actions\ActualizarInscripcionPagadaAction.
     */
    public function validateDuplicateParticipantsFromData(array $data): void
    {
        $documents = [];

        foreach ($data['participantes'] as $participant) {
            $key = ($participant['tipoDocumento'] ?? 'DNI') . '-' . $participant['numeroDocumento'];
            if (isset($documents[$key])) {
                throw new \DomainException(
                    "El participante con documento {$participant['numeroDocumento']} está repetido en la solicitud."
                );
            }
            $documents[$key] = true;
        }
    }

    /**
     * Marca un código de promoción como usado por esta inscripción, o lanza
     * si ya lo consumió otra. lockForUpdate() dentro de la transacción de
     * quien llama (App\Actions\CrearInscripcionAction/
     * ActualizarInscripcionAction/ActualizarInscripcionPagadaAction) evita
     * que dos inscripciones simultáneas con el mismo código pasen ambas la
     * validación. Público — colaborador compartido por las 3 Actions.
     *
     * `$formTypeId` es nuevo (09/08): `hasPromoCode` vive en `form_types`,
     * no en `eventos` — defensa en profundidad, nunca confiar solo en que
     * elascenso/event o elascenso-blade ya lo validaron antes de llamar
     * acá.
     */
    public function consumePromoCode(int $eventId, string $promoCodigo, int $registrationId, int $formTypeId): void
    {
        $promoCodigo = trim($promoCodigo);
        if ($promoCodigo === '') return;

        $formType = FormType::find($formTypeId);
        if (! $formType?->has_promo_code) {
            throw new \DomainException('Este tipo de formulario no admite códigos promocionales.');
        }

        // BINARY para que la comparación sea case-sensitive igual que en
        // PromoCodeController::promoCode() — la columna tiene collation
        // case-insensitive por defecto.
        $promo = PromoCode::where('event_id', $eventId)
            ->whereRaw('BINARY promo_code = ?', [$promoCodigo])
            ->lockForUpdate()
            ->first();

        // Si no existe, ya debería haberse rechazado aguas arriba en
        // elascenso/event (_registro_validacion.php) — acá no bloqueamos por
        // un código desconocido, solo por uno ya usado por otra inscripción.
        if (!$promo) return;

        if ($promo->usado && $promo->registration_id !== $registrationId) {
            throw new \DomainException('Este código de promoción ya fue utilizado.');
        }

        $promo->update(['usado' => true, 'registration_id' => $registrationId]);
    }

    /**
     * Libera los códigos de promoción que una inscripción tiene consumidos —
     * se llama antes de recrear sus participantes en
     * App\Actions\ActualizarInscripcionAction/ActualizarInscripcionPagadaAction,
     * para no rechazarla a sí misma si mantiene el mismo código y para
     * liberar el código si lo cambia o lo quita.
     */
    public function releasePromoCodes(int $registrationId): void
    {
        PromoCode::where('registration_id', $registrationId)
            ->update(['usado' => false, 'registration_id' => null]);
    }

    /**
     * Cargar relaciones — colaborador compartido por las 3 Actions de
     * inscripción y por findByReference()/updatePaymentStatus() acá abajo.
     */
    public function loadRelations(
        Registration $registration
    ): Registration {

        return $registration->load([
           'totals',
           'participants.contactoEmergenciaParticipante',
           'participants.souvenirParticipante',
           'participants.answers',
        ]);
    }

    /**
     * Sincronizar participantes como personas.
     * Crea o actualiza una Persona por cada participante de la inscripción.
     * El password es el número de documento.
     *
     * Público — colaborador compartido por las 3 Actions de inscripción
     * (Crear/Actualizar/ActualizarPagada).
     */
    public function syncPersonas(Registration $registration): void
    {
        $participants = $registration->load('participants')->participants;

        foreach ($participants as $participante) {
            $persona = Persona::where('numero_documento', $participante->numero_documento)
                ->orWhere('email', $participante->correo)
                ->first();

            $data = [
                'tipo_documento'   => $participante->tipo_documento,
                'nombre'           => $participante->nombre,
                'apellido'         => $participante->apellido,
                'alias'            => $participante->alias,
                'sexo'             => $participante->genero,
                'email'            => $participante->correo,
                'correo'           => $participante->correo,
                'password'         => Hash::make($participante->numero_documento),
                'direccion'        => $participante->direccion,
                'ciudad'           => $participante->ciudad,
                'telefono'         => $participante->telefono,
                'celular'          => $participante->telefono,
                'fecha_nacimiento' => $participante->fecha_nacimiento,
            ];

            if ($persona) {
                $persona->update($data);
            } else {
                Persona::create(array_merge($data, [
                    'numero_documento' => $participante->numero_documento,
                    'token'            => Str::random(40),
                ]));
            }
        }
    }

    /**
     * Buscar inscripción por credenciales de persona, evento y form_type.
     * Retorna la inscripción o solo los datos de la persona.
     */
    public function lookupRegistration(
        string $email,
        string $password,
        int $eventoId,
        int $formTypeId
    ): array {

        $persona = Persona::where('email', $email)->first();

        if (!$persona || !Hash::check($password, $persona->password)) {
            throw new \DomainException('Credenciales inválidas.');
        }

        $registration = Registration::with([
                'totals',
                'participants.contactoEmergenciaParticipante',
                'participants.souvenirParticipante',
                'participants.answers',
            ])
            ->where('evento_id', $eventoId)
            ->where('form_types_id', $formTypeId)
            ->whereHas('participants', function ($q) use ($persona) {
                $q->where('numero_documento', $persona->numero_documento)
                  ->orWhere('correo', $persona->email);
            })
            ->orderByDesc('fecha')
            ->first();

        if ($registration) {
            return [
                'type' => 'registration',
                'data' => $registration,
            ];
        }

        $token = $persona->createToken('auth-token')->plainTextToken;

        return [
            'type'   => 'persona',
            'data'   => $persona->load('contactoEmergencia'),
            'token'  => $token,
        ];
    }
}