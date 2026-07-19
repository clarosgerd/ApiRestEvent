<?php

namespace App\Notifications\Messages;

class WhatsAppMessage
{
    public function __construct(
        public readonly string $content,
    ) {}

    public static function create(string $content): static
    {
        return new static($content);
    }
}
