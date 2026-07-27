<?php

namespace App\Mail;

use App\Models\Registration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PagoConfirmadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
    }

    private const PAY_LABELS = ['sip' => 'QR Code', 'multipago' => 'Multipago', 'pendiente' => 'Pay later'];

    public function build(): self
    {
        $evento    = $this->registration->evento;
        $payLabel  = self::PAY_LABELS[$this->registration->tipo_pago] ?? $this->registration->tipo_pago;

        $mail = $this->subject("Payment Confirmation — {$this->registration->evento_nombre} [{$this->registration->referencia}]")
            ->view('emails.confirmacion')
            ->with([
                'registration' => $this->registration,
                'evento'       => $evento,
                'headerTitle'  => 'Payment Confirmation',
                'statusLabel'  => '✓ Paid',
                'statusColor'  => '#258f36',
                'footerMsg'    => 'Keep this email as proof of payment',
                'payLabel'     => $payLabel,
            ]);

        $pdf = Pdf::loadView('tickets.eticket', [
            'registration' => $this->registration,
            'evento'       => $evento,
            'payLabel'     => $payLabel,
        ]);

        return $mail->attachData(
            $pdf->output(),
            "eticket-{$this->registration->referencia}.pdf",
            ['mime' => 'application/pdf']
        );
    }
}
