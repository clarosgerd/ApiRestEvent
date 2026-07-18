<?php

namespace App\DTOs;

class QuestionOptionDTO
{
    public function __construct(
        public string $optionText,
        public int $order,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            optionText: $data['option_text'],
            order: (int) ($data['order'] ?? 0),
        );
    }
}
