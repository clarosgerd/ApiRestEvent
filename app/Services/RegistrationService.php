<?php

namespace App\Services;

use App\DTOs\ParticipantDTO;
use App\DTOs\RegistrationDTO;
use App\Models\Participante;
use App\Models\Persona;
use App\Models\SouvenirParticipante;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Models\ContactoEmergenciaParticipante;
use App\Models\Answer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
class RegistrationService
{
    /**
     * Crear una inscripción completa.
     */
    public function create(RegistrationDTO $dto): Registration
    {
        return DB::transaction(function () use ($dto) {

            if (
                Registration::where('referencia', $dto->reference)->exists()
            ) {
                throw new \Exception(
                    "La referencia {$dto->reference} ya existe."
                );
            }
            
            foreach ($dto->participants as $participant) {
                $this->validateParticipantRegistration($dto, $participant);
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
            ]);

            foreach ($dto->participants as $participant) {
                $this->createParticipant(
                    $registration,
                    $participant
                );
            }
            $this->createTotals(
                $registration,
                $dto
            );

            $this->syncPersonas($registration);
            
            return $this->loadRelations(
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
      //  dd($dto);
        foreach ($dto->souvenirs as $souvenir) {

            SouvenirParticipante::create([

                'participante_id' => $participant->id,
                'souvenir_id' => $souvenir->souvenir_id,
                'nombre' => $souvenir->name,
                'precio' => $souvenir->price,

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
     * Actualizar inscripción completa (solo si no está pagada).
     */
    public function update(string $reference, array $data): Registration
    {
        return DB::transaction(function () use ($reference, $data) {

            $registration = Registration::where('referencia', $reference)->firstOrFail();

            if ($registration->pago_status === 'paid') {
                throw new \DomainException(
                    'No se puede modificar una inscripción ya pagada.'
                );
            }

            $this->validateDuplicateParticipantsFromData($data);

            $registration->participants()->delete();
            $registration->totals()->delete();

            foreach ($data['participantes'] as $participantData) {
                $this->createParticipantFromData($registration, $participantData);
            }

            RegistrationTotal::create([
                'registration_id' => $registration->id,
                'inscripcion'     => $data['totales']['inscripcion'],
                'donacion'        => $data['totales']['donacion'],
                'souvenirs'       => $data['totales']['souvenirs'],
                'fee'             => $data['totales']['fee'],
                'descuento'       => $data['totales']['descuento'],
                'grand_total'     => $data['totales']['grand_total'],
            ]);

            $this->syncPersonas($registration);

            return $this->loadRelations($registration);
        });
    }

    /**
     * Crear participante desde array raw (para actualización).
     */
    private function createParticipantFromData(Registration $registration, array $data): void
    {
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
     * Validar que no hayan documentos duplicados en el request de actualización.
     */
    private function validateDuplicateParticipantsFromData(array $data): void
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
     * Cargar relaciones.
     */
    private function loadRelations(
        Registration $registration
    ): Registration {

        return $registration->load([
           'totals',
           'participants.contactoEmergenciaParticipante',
           'participants.souvenirParticipante',
           'participants.answers',
        ]);
    }

private function validateParticipantRegistration(
    RegistrationDTO $registrationDTO,
    ParticipantDTO $participantDTO
): void {

    $exists = Participante::query()
        ->where('tipo_documento', $participantDTO->documentType)
        ->where('numero_documento', $participantDTO->documentNumber)
        ->whereHas('registration', function ($query) use ($registrationDTO) {
            $query->where('evento_id', $registrationDTO->eventId);
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

    /**
     * Sincronizar participantes como personas.
     * Crea o actualiza una Persona por cada participante de la inscripción.
     * El password es el número de documento.
     */
    private function syncPersonas(Registration $registration): void
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
}