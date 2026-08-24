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
                'linkPago'     => $linkPago,
                'expiraEn'     => $expiraEn,
            ]);
    }
}
