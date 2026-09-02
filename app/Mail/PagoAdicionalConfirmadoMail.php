<?php

namespace App\Mail;

use App\Models\PagoAdicionalInscripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de confirmación por pago adicional (02/09/2026) — ver
 * NotificacionService::notificarPagoAdicionalConfirmado() y
 * PLAN-COBRO-SIP-ADICIONAL-26082026.md. Distinto de PagoConfirmadoMail
 * (que es la inscripción original, con e-ticket adjunto) — este es un
 * aviso puntual de "se cobró y aplicó tu pago adicional", sin PDF.
 */
class PagoAdicionalConfirmadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PagoAdicionalInscripcion $pago)
    {
    }

    public function build(): self
    {
        $registration = $this->pago->registration;

        return $this->subject("Pago adicional confirmado — {$registration->evento_nombre} [{$registration->referencia}]")
            ->view('emails.pago-adicional-confirmado')
            ->with([
                'registration' => $registration,
                'evento'       => $registration->evento,
                'pago'         => $this->pago,
            ]);
    }
}
