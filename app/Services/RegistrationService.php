<?php

namespace App\Services;

use App\DTOs\ParticipantDTO;
use App\DTOs\RegistrationDTO;
use App\Models\EmergencyContact;
use App\Models\Participante;
use App\Models\SouvenirParticipante;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Models\ContactoEmergenciaParticipante;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

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
            //    $this->createParticipant($registration, $participant);
            }

            $this->validateDuplicateParticipants($dto);

            foreach ($dto->participants as $participant) {
                $this->validateParticipantRegistration($dto, $participant);
            }


            

            $registration = Registration::create([
                'referencia' => $dto->reference,
                'fecha' => $dto->date,
                'evento_id' => $dto->eventId,
                'evento_nombre' => $dto->eventName,
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
     * Cargar relaciones.
     */
    private function loadRelations(
        Registration $registration
    ): Registration {

        return $registration->load([
           'totals',
           'participants.contactoEmergenciaParticipante',
           'participants.souvenirParticipante'
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
}