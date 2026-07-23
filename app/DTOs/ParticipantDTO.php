<?php
namespace App\DTOs;

class ParticipantDTO
{
    public function __construct(
         public string $firstName,
        public string $lastName,
        public string $alias,
        public string $gender,
        public string $documentType,
        public string $documentNumber,
        public string $shirt,
        public float $shirtPrice,
        public BirthDateDTO $birthDate,
        public int $age,
        public string $email,
        public string $address,
        public string $city,
        public string $phone,
        public ContactoEmergenciaParticipanteDTO $emergencyContact,

        /** @var SouvenirDTO[] */
        public array $souvenirs,

        /** @var AnswerDTO[] */
        public array $answers,

        public string $category,
        public float $categoryPrice,
        public float $donation,
        public float $promoDiscount,
        public string $promoCode,
        public float $subtotal

    ){}

    public static function fromArray(array $data): self
    {

    //dd($data);
        return new self(
            firstName: $data['nombre'],
            lastName: $data['apellido'],
            alias: $data['alias'] ?? '',
            gender: $data['genero'] ?? 'Masculino',
            documentType: $data['tipoDocumento'] ?? 'DNI',
            documentNumber: $data['numeroDocumento'],
            shirt: $data['polera'] ?? '',
            shirtPrice: (float) $data['precioPolera'] ?? 0 ,
            birthDate: BirthDateDTO::fromArray($data['nacimiento']),
            age: (int) $data['edad'],
            email: $data['correo'],
            address: $data['direccion'],
            city: $data['ciudad'],
            phone: $data['telefono'],
            emergencyContact: ContactoEmergenciaParticipanteDTO::fromArray($data['contacto_emergencia'] ?? []), 
            souvenirs: array_map(
                fn(array $souvenir) => SouvenirParticipanteDTO::fromArray($souvenir),
                $data['souvenirs'] ?? []
            ),
            answers: array_map(
                fn(array $a) => AnswerDTO::fromArray($a),
                $data['answers'] ?? []
            ),

            category: (string) $data['categoria'],
            categoryPrice: (float) $data['precioCategoria'],
            donation: (float) $data['donacion'],
            promoDiscount: (float) $data['promoDescuento'],
            promoCode: $data['promoCodigo'] ?? '',
            subtotal: (float) $data['subtotal']
        );
    }
}