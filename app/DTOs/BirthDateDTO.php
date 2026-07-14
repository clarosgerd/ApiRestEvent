<?php
namespace App\DTOs;

class BirthDateDTO
{
    public function __construct(
        public int $day,
        public int $month,
        public int $year

    ){}
      public static function fromArray(array $data): self
    {
        return new self(
            day: (int) $data['dia'],
            month: (int) $data['mes'],
            year: (int) $data['anio']
        );
    }
}