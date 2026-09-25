<?php

namespace App\Mail;

use App\Models\EmpresaExpositora;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * SmartStand (25/09/2026) — acceso de la empresa expositora: usuario,
 * contraseña y links de la app de escaneo. Mailable aparte del comprobante de
 * pago (PagoConfirmadoMail) para no mezclar credenciales con el comprobante, y
 * liviano a propósito (sin PDF adjunto): se dispara dentro del callback de la
 * pasarela, que tiene un timeout corto.
 *
 * La contraseña en texto plano solo existe acá, en memoria, durante el envío
 * — en la BD se guarda únicamente el hash.
 */
class ExpositorCredencialesMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmpresaExpositora $cuenta,
        public string $passwordPlano,
    ) {
    }

    public function build(): self
    {
        $evento = $this->cuenta->evento;
        $config = $evento?->expositores_config ?? [];

        return $this->subject('Tu acceso de expositor — ' . ($evento?->nombre ?? 'evento'))
            ->view('emails.expositor-credenciales')
            ->with([
                'cuenta'       => $this->cuenta,
                'evento'       => $evento,
                'passwordPlano' => $this->passwordPlano,
                'categoria'    => $this->cuenta->categoria?->name,
                'appUrlIos'    => $config['app_url_ios'] ?? null,
                'appUrlAndroid' => $config['app_url_android'] ?? null,
                'instrucciones' => $config['instrucciones'] ?? null,
                // Link de ingreso: el del evento y, si no lo configuró, el
                // del sitio público (config/services.php). Sin él la empresa
                // recibe usuario y contraseña pero no sabe dónde usarlos.
                'dashboardUrl' => ($config['dashboard_url'] ?? null) ?: config('services.smartstand.panel_url'),
            ]);
    }
}
