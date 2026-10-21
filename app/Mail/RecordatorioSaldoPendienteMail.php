<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecordatorioSaldoPendienteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
    }

    public function build(): self
    {
        $evento = $this->registration->evento;

        return $this->subject("Recordatorio: saldo pendiente de tu reserva — {$this->registration->evento_nombre} [{$this->registration->referencia}]")
            ->view('emails.recordatorio-saldo-pendiente')
            ->with([
                'registration' => $this->registration,
                'evento'       => $evento,
                'fechaLimiteSaldo' => $evento?->fechaLimiteSaldoReserva(),
            ]);
    }
}
