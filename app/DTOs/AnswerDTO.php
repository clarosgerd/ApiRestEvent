<?php
namespace App\DTOs;

class AnswerDTO
{
    public function __construct(
        public int $formTypeId,
        public int $questionId,
        public string $value,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            formTypeId: (int) $data['form_types_id'],
            questionId: (int) $data['question_id'],
            value: (string) $data['value'],
        );
    }
}
