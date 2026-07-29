<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecordatorioKitMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
    }

    public function build(): self
    {
        $evento = $this->registration->evento;

        return $this->subject("Recordatorio de recojo de kit — {$this->registration->evento_nombre} [{$this->registration->referencia}]")
            ->view('emails.recordatorio-kit')
            ->with([
                'registration' => $this->registration,
                'evento'       => $evento,
            ]);
    }
}
