<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InscripcionPendienteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
    }

    private const PAY_LABELS = ['sip' => 'QR Code', 'multipago' => 'Multipago', 'pendiente' => 'Pay later'];

    public function build(): self
    {
        $evento = $this->registration->evento;

        return $this->subject("Registration Received — Payment Pending — {$this->registration->evento_nombre} [{$this->registration->referencia}]")
            ->view('emails.confirmacion')
            ->with([
                'registration' => $this->registration,
                'evento'       => $evento,
                'headerTitle'  => 'Registration Received — Payment Pending',
                'statusLabel'  => '⏳ Pending',
                'statusColor'  => '#b07d00',
                'footerMsg'    => 'Keep this email as your registration reference',
                'payLabel'     => self::PAY_LABELS[$this->registration->tipo_pago] ?? $this->registration->tipo_pago,
            ]);
    }
}
