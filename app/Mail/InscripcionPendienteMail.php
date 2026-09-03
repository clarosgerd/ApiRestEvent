<?php

namespace App\Mail;

use App\Models\Registration;
use App\Services\ReferenceQrService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InscripcionPendienteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
    }

    private const PAY_LABELS = ['sip' => 'QR (SIP)', 'multipago' => 'Multipago', 'pendiente' => 'Pago pendiente', 'pendiente_usd' => 'Pago pendiente (USD)'];

    public function build(): self
    {
        $evento = $this->registration->evento?->loadMissing('organizador');

        // Pago pendiente USD (24/08/2026) — el link sale del organizador del
        // evento (Organizador::linkPagoPendienteUsd(), config por
        // organizador, no por evento), no de una columna del evento. Para
        // cualquier otro método (sip/multipago/pendiente) queda null y el
        // bloque del link no se renderiza — ver
        // resources/views/emails/confirmacion.blade.php.
        $esPendienteUsd = $this->registration->tipo_pago === 'pendiente_usd';
        $linkPago = $esPendienteUsd ? $evento?->organizador?->linkPagoPendienteUsd() : null;
        $expiraEn = $linkPago ? $this->registration->created_at->copy()->addHours(24) : null;
        $payLabel = self::PAY_LABELS[$this->registration->tipo_pago] ?? $this->registration->tipo_pago;
        $qrImage  = ReferenceQrService::toBase64Png($this->registration->referencia);

        $mail = $this->subject("Registro recibido — Pago pendiente — {$this->registration->evento_nombre} [{$this->registration->referencia}]")
            ->view('emails.confirmacion')
            ->with([
                'registration' => $this->registration,
                'evento'       => $evento,
                'headerTitle'  => 'Registro recibido — Pago pendiente',
                'statusLabel'  => '⏳ Pendiente',
                'statusColor'  => '#b07d00',
                'footerMsg'    => 'Guarda este correo como referencia de tu registro',
                'payLabel'     => $payLabel,
                'qrImage'      => $qrImage,
                'linkPago'     => $linkPago,
                'expiraEn'     => $expiraEn,
            ]);

        // PDF adjunto con el QR (03/09/2026) — bug real reportado por un
        // usuario: el QR embebido como data: URI en el cuerpo del correo
        // le llegaba como imagen rota ("segunda vez", no era casual —
        // muchos clientes de correo bloquean imágenes data: por defecto).
        // Mismo mecanismo que ya usa PagoConfirmadoMail (dompdf no
        // depende del bloqueo de imágenes del cliente de correo, es
        // confiable) — antes este correo no adjuntaba ningún PDF, el QR
        // inline era la única forma de verlo.
        $pdf = Pdf::loadView('tickets.eticket', [
            'registration' => $this->registration,
            'evento'       => $evento,
            'payLabel'     => $payLabel,
            'qrImage'      => $qrImage,
            'pdfTitle'     => 'Comprobante de registro',
            'statusLabel'  => '⏳ Pendiente',
            'statusColor'  => '#b07d00',
            'pdfFooterMsg' => 'Guarda este comprobante como referencia de tu registro',
        ]);

        return $mail->attachData(
            $pdf->output(),
            "comprobante-{$this->registration->referencia}.pdf",
            ['mime' => 'application/pdf']
        );
    }
}
