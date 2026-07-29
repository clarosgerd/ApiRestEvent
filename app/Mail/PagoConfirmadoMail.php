<?php

namespace App\Mail;

use App\Models\Registration;
use App\Services\ReferenceQrService;
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

    private const PAY_LABELS = ['sip' => 'QR (SIP)', 'multipago' => 'Multipago', 'pendiente' => 'Pago pendiente'];

    public function build(): self
    {
        $evento    = $this->registration->evento;
        $payLabel  = self::PAY_LABELS[$this->registration->tipo_pago] ?? $this->registration->tipo_pago;
        $qrImage   = ReferenceQrService::toBase64Png($this->registration->referencia);

        $mail = $this->subject("Confirmación de pago — {$this->registration->evento_nombre} [{$this->registration->referencia}]")
            ->view('emails.confirmacion')
            ->with([
                'registration' => $this->registration,
                'evento'       => $evento,
                'headerTitle'  => 'Confirmación de pago',
                'statusLabel'  => '✓ Pagado',
                'statusColor'  => '#258f36',
                'footerMsg'    => 'Guarda este correo como comprobante de pago',
                'payLabel'     => $payLabel,
                'qrImage'      => $qrImage,
            ]);

        $pdf = Pdf::loadView('tickets.eticket', [
            'registration' => $this->registration,
            'evento'       => $evento,
            'payLabel'     => $payLabel,
            'qrImage'      => $qrImage,
        ]);

        return $mail->attachData(
            $pdf->output(),
            "eticket-{$this->registration->referencia}.pdf",
            ['mime' => 'application/pdf']
        );
    }
}
