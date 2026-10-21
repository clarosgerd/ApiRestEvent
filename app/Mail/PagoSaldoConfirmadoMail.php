<?php

namespace App\Mail;

use App\Models\PagoSaldoInscripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Pago online del saldo pendiente — autoservicio (22/10/2026). Espejo de
 * PagoAdicionalConfirmadoMail.
 */
class PagoSaldoConfirmadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PagoSaldoInscripcion $pago)
    {
    }

    public function build(): self
    {
        $registration = $this->pago->registration;

        return $this->subject("Saldo pagado — {$registration->evento_nombre} [{$registration->referencia}]")
            ->view('emails.pago-saldo-confirmado')
            ->with([
                'registration' => $registration,
                'evento'       => $registration->evento,
                'pago'         => $this->pago,
            ]);
    }
}
