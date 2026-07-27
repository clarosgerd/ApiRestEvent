<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CupoRevertidoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
    }

    public function build(): self
    {
        return $this->subject("Registration Cancelled — {$this->registration->evento_nombre} [{$this->registration->referencia}]")
            ->view('emails.cupo-revertido')
            ->with([
                'registration' => $this->registration,
                'evento'       => $this->registration->evento,
            ]);
    }
}
