<?php

namespace App\Mail;

use App\Models\Registration;
use App\Services\ReferenceQrService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InscripcionPendienteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
    }

    private const PAY_LABELS = ['sip' => 'QR (SIP)', 'multipago' => 'Multipago', 'pendiente' => 'Pago pendiente'];

    public function build(): self
    {
        $evento = $this->registration->evento;

        return $this->subject("Registro recibido — Pago pendiente — {$this->registration->evento_nombre} [{$this->registration->referencia}]")
            ->view('emails.confirmacion')
            ->with([
                'registration' => $this->registration,
                'evento'       => $evento,
                'headerTitle'  => 'Registro recibido — Pago pendiente',
                'statusLabel'  => '⏳ Pendiente',
                'statusColor'  => '#b07d00',
                'footerMsg'    => 'Guarda este correo como referencia de tu registro',
                'payLabel'     => self::PAY_LABELS[$this->registration->tipo_pago] ?? $this->registration->tipo_pago,
                'qrImage'      => ReferenceQrService::toBase64Png($this->registration->referencia),
            ]);
    }
}
